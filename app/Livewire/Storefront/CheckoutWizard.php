<?php

namespace App\Livewire\Storefront;

use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CheckoutWizard extends Component
{
    public int $step = 1;

    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';

    public ?int $address_id = null;
    public string $delivery_name = '';
    public string $delivery_full_address = '';
    public string $delivery_city = '';
    public string $delivery_region = '';
    public string $delivery_postcode = '';
    public string $delivery_country = 'LV';
    public string $delivery_phone = '';

    public int $shipping_method_id = 0;
    public int $payment_method_id = 0;
    public string $comment = '';

    protected function casts(): array
    {
        return [
            'address_id' => 'integer',
            'shipping_method_id' => 'integer',
            'payment_method_id' => 'integer',
        ];
    }

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->customer_name = $user->name;
            $this->customer_email = $user->email;
            $this->customer_phone = (string) $user->phone;
        }
    }

    public function getCartProperty()
    {
        return app(CartService::class)->getOrCreateCart()
            ->load(['cartItems.product']);
    }

    public function getShippingMethodsProperty()
    {
        return ShippingMethod::active()->get();
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::active()->get();
    }

    public function getAddressesProperty()
    {
        return Auth::user()?->addresses()->shipping()->orderBy('is_default', 'desc')->get() ?? collect();
    }

    public function getSubtotalProperty(): float
    {
        return (float) $this->cart->cartItems->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getShippingCostProperty(): float
    {
        if ($this->shipping_method_id <= 0) {
            return 0;
        }
        $method = ShippingMethod::find($this->shipping_method_id);
        if (!$method) {
            return 0;
        }
        if ($method->free_from !== null && $this->subtotal >= (float) $method->free_from) {
            return 0;
        }
        return (float) $method->cost;
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->shippingCost;
    }

    public function getSelectedAddressProperty(): ?Address
    {
        if ($this->address_id <= 0) {
            return null;
        }
        return $this->addresses->firstWhere('id', $this->address_id);
    }

    public function rules(): array
    {
        $step1 = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
        ];

        $step2 = [
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ];

        if ($this->address_id) {
            $step2['address_id'] = ['required', 'integer', 'exists:addresses,id'];
        } else {
            $step2['delivery_name'] = ['required', 'string', 'max:255'];
            $step2['delivery_full_address'] = ['required', 'string', 'max:500'];
            $step2['delivery_city'] = ['required', 'string', 'max:100'];
            $step2['delivery_region'] = ['nullable', 'string', 'max:100'];
            $step2['delivery_postcode'] = ['nullable', 'string', 'max:20'];
            $step2['delivery_country'] = ['required', 'string', 'size:2'];
            $step2['delivery_phone'] = ['nullable', 'string', 'max:50'];
        }

        return match ($this->step) {
            1 => $step1,
            2 => $step2,
            default => [],
        };
    }

    public function step1Next(): void
    {
        $this->validate($this->rules());
        $this->step = 2;
    }

    public function step2Next(): void
    {
        $this->validate($this->rules());
        $this->step = 3;
    }

    public function stepBack(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function placeOrder(): void
    {
        $this->validate($this->rules());

        $cart = $this->cart;
        if ($cart->cartItems->isEmpty()) {
            $this->addError('cart', 'Корзина пуста.');
            return;
        }

        $customerData = [
            'name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone ?: null,
        ];

        if ($this->address_id && $addr = $this->selectedAddress) {
            $deliveryData = [
                'name' => $addr->name ?? $this->customer_name,
                'full_address' => $addr->full_address,
                'city' => $addr->city,
                'region' => $addr->region,
                'postcode' => $addr->postcode,
                'country' => $addr->country,
                'phone' => $addr->phone,
            ];
        } else {
            $deliveryData = [
                'name' => $this->delivery_name,
                'full_address' => $this->delivery_full_address,
                'city' => $this->delivery_city,
                'region' => $this->delivery_region ?: null,
                'postcode' => $this->delivery_postcode ?: null,
                'country' => $this->delivery_country,
                'phone' => $this->delivery_phone ?: null,
            ];
        }

        try {
            $order = app(OrderService::class)->createFromCart(
                $cart,
                $customerData,
                $deliveryData,
                $this->shipping_method_id,
                $this->payment_method_id,
                $this->comment ?: ''
            );
            $this->dispatch('cart-updated');
            session()->flash('success', 'Заказ #' . $order->id . ' успешно оформлен.');
            $this->redirect(route('account.orders.show', $order), navigate: true);
        } catch (\Throwable $e) {
            $this->addError('order', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.storefront.checkout-wizard')
            ->layout('layouts.storefront', ['title' => 'Оформление заказа']);
    }
}
