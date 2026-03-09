<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Mail\OrderConfirmedMail;
use App\Models\OrderStatus;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function createFromCart(
        Cart $cart,
        array $customerData,
        array $deliveryData,
        int $shippingMethodId,
        int $paymentMethodId,
        string $comment = ''
    ): Order {
        $cart->load(['cartItems.product']);

        if ($cart->cartItems->isEmpty()) {
            throw new \InvalidArgumentException('Корзина пуста.');
        }

        $status = OrderStatus::where('slug', 'new')->firstOrFail();
        $shippingMethod = ShippingMethod::findOrFail($shippingMethodId);

        $subtotal = 0;
        foreach ($cart->cartItems as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        $shippingCost = $this->calculateShippingCost($subtotal, $shippingMethod);
        $total = $subtotal + $shippingCost;

        return DB::transaction(function () use (
            $cart,
            $customerData,
            $deliveryData,
            $status,
            $shippingMethod,
            $shippingMethodId,
            $paymentMethodId,
            $subtotal,
            $shippingCost,
            $total,
            $comment
        ) {
            $order = Order::create([
                'user_id' => $cart->user_id,
                'company_id' => null,
                'status_id' => $status->id,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'customer_name' => $customerData['name'],
                'customer_email' => $customerData['email'],
                'customer_phone' => $customerData['phone'] ?? null,
                'shipping_method_id' => $shippingMethodId,
                'payment_method_id' => $paymentMethodId,
                'comment' => $comment ?: null,
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'name' => $deliveryData['name'],
                'full_address' => $deliveryData['full_address'],
                'city' => $deliveryData['city'],
                'region' => $deliveryData['region'] ?? null,
                'postcode' => $deliveryData['postcode'] ?? null,
                'country' => $deliveryData['country'] ?? 'LV',
                'phone' => $deliveryData['phone'] ?? null,
            ]);

            foreach ($cart->cartItems as $item) {
                $product = $item->product;
                $itemTotal = $item->price * $item->quantity;
                $vatRate = $product->vat_rate;
                $vatAmount = $vatRate ? round($itemTotal * (float) $vatRate / 100, 2) : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $itemTotal,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'seller_id' => $item->seller_id,
                ]);
            }

            $cart->cartItems()->delete();
            $order = $order->fresh(['orderAddresses', 'orderItems']);

            try {
                Mail::to($order->customer_email)->send(new OrderConfirmedMail($order));
            } catch (\Throwable $e) {
                report($e);
            }

            return $order;
        });
    }

    protected function calculateShippingCost(float $subtotal, ShippingMethod $method): float
    {
        if ($method->free_from !== null && $subtotal >= (float) $method->free_from) {
            return 0;
        }
        return (float) $method->cost;
    }
}
