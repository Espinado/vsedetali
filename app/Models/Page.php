<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    /**
     * Slug зашит во витрине (меню, ссылки). При правке страницы URL не пересобирается из заголовка.
     *
     * @var list<string>
     */
    public const LOCKED_SLUGS = ['contacts', 'delivery', 'payment'];

    protected $fillable = [
        'title',
        'meta_title',
        'meta_description',
        'slug',
        'body',
        'contact_email',
        'contact_phone',
        'contact_address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isContactsPage(): bool
    {
        return $this->slug === 'contacts';
    }
}
