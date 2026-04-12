<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    use HasFactory;

    /**
     * Категория для товаров с модерации продавцов ({@see \App\Services\SellerSubmittedProductService}).
     */
    public const MARKETPLACE_MODERATION_SLUG = 'seller-marketplace';

    /**
     * Slug нельзя менять автогенерацией из админки (связаны с кодом).
     *
     * @var list<string>
     */
    public const LOCKED_SLUGS = [
        self::MARKETPLACE_MODERATION_SLUG,
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'sort',
        'is_active',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Цепочка от корня до этой категории (включительно) для хлебных крошек.
     * Только активные категории; служебные (slug import-*) в цепочку не попадают.
     */
    public function ancestorsChainForStorefront(): Collection
    {
        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');

        $chain = collect([$this]);
        $current = $this;

        while ($current->parent_id) {
            $parent = static::query()
                ->active()
                ->whereKey($current->parent_id)
                ->first();

            if (! $parent || str_starts_with((string) $parent->slug, $prefix)) {
                break;
            }

            $chain->prepend($parent);
            $current = $parent;
        }

        return $chain;
    }
}
