<?php

namespace App\Models;

use App\Services\CustomerBlockingService;
use Illuminate\Database\Eloquent\Model;

class CustomerBlock extends Model
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_IP = 'ip';

    public const TYPE_MAC = 'mac';

    protected $fillable = [
        'type',
        'value',
        'reason',
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_EMAIL,
            self::TYPE_IP,
            self::TYPE_MAC,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CustomerBlock $block): void {
            $service = app(CustomerBlockingService::class);
            $block->value = match ($block->type) {
                self::TYPE_EMAIL => $service->normalizeEmail($block->value),
                self::TYPE_IP => $service->normalizeIp($block->value),
                self::TYPE_MAC => $service->normalizeMac($block->value),
                default => trim($block->value),
            };
        });
    }
}
