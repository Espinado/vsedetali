<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'status_id',
        'subtotal',
        'shipping_cost',
        'total',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_method_id',
        'payment_method_id',
        'comment',
        'invoice_number',
        'deferred_payment_until',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'deferred_payment_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderAddresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function latestShipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function shippingAddress(): ?OrderAddress
    {
        return $this->orderAddresses()->where('type', 'shipping')->first();
    }

    /** Заказы, где есть хотя бы одна позиция этого продавца (маркетплейс). */
    public function scopeWithSellerItems(Builder $query, int $sellerId): Builder
    {
        return $query->whereHas('orderItems', fn (Builder $q) => $q->where('seller_id', $sellerId));
    }

    /** Только продажи площадки: нет позиций с привязкой к продавцу маркетплейса. */
    public function scopePlatformSalesOnly(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'orderItems',
            fn (Builder $q) => $q->whereNotNull('seller_id'),
        );
    }

    /** Есть хотя бы одна позиция маркетплейс-продавца. */
    public function scopeWithMarketplaceSellerItems(Builder $query): Builder
    {
        return $query->whereHas(
            'orderItems',
            fn (Builder $q) => $q->whereNotNull('seller_id'),
        );
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->latestPayment?->status) {
            'paid' => 'Оплачено',
            'pending' => 'Ожидает оплаты',
            'failed' => 'Ошибка оплаты',
            default => 'Не оплачено',
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match ($this->latestPayment?->status) {
            'paid' => 'rgb(22 163 74)',
            'pending' => 'rgb(217 119 6)',
            'failed' => 'rgb(220 38 38)',
            default => 'rgb(100 116 139)',
        };
    }

    public function getShipmentStatusLabelAttribute(): string
    {
        return match ($this->latestShipment?->status) {
            'pending' => 'Ожидает сборки',
            'packed' => 'Собран',
            'shipped' => 'Отгружен',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отгрузка отменена',
            default => 'Отгрузка не создана',
        };
    }

    public function getShipmentStatusColorAttribute(): string
    {
        return match ($this->latestShipment?->status) {
            'pending' => 'rgb(217 119 6)',
            'packed' => 'rgb(139 92 246)',
            'shipped' => 'rgb(8 145 178)',
            'delivered' => 'rgb(22 163 74)',
            'cancelled' => 'rgb(220 38 38)',
            default => 'rgb(100 116 139)',
        };
    }
}
