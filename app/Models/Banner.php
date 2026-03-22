<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'link',
        'sort',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Нормализованный URL для ссылки или null, если ссылка не задана.
     */
    public function resolvedHref(): ?string
    {
        $raw = trim((string) ($this->link ?? ''));
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, '/')) {
            return url($raw);
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        return 'https://'.ltrim($raw, '/');
    }

    public function linkOpensInNewTab(): bool
    {
        $raw = trim((string) ($this->link ?? ''));

        return $raw !== '' && ! str_starts_with($raw, '/');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort');
    }
}
