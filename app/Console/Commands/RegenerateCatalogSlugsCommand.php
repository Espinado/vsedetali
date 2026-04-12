<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Seller;
use App\Support\BrandCatalogSlug;
use App\Support\CategoryCatalogSlug;
use App\Support\MarketplaceSellerSlug;
use App\Support\PageCatalogSlug;
use App\Support\ProductCatalogSlug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Пересборка slug из человекочитаемых полей (Str::slug → латиница).
 * Статусы заказов (order_statuses) не трогаем — на них завязан код.
 */
class RegenerateCatalogSlugsCommand extends Command
{
    protected $signature = 'catalog:regenerate-slugs
                            {--dry-run : Показать изменения без записи в БД}
                            {--chunk=500 : Размер чанка для товаров и тяжёлых таблиц}';

    protected $description = 'Пересобрать slug брендов, категорий, товаров, страниц и продавцов. Защищённые slug (площадка, витрина, бренд-заглушка) не меняются.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));

        if ($dry) {
            $this->warn('Режим dry-run: уникальность считается по текущему состоянию БД; после реального запуска часть суффиксов может отличаться.');
        }

        $this->regenerateBrands($dry);
        $this->regenerateCategories($dry);
        $this->regenerateProducts($dry, $chunk);
        $this->regeneratePages($dry);
        $this->regenerateSellers($dry);

        $this->info($dry ? 'Dry-run завершён.' : 'Готово.');

        return self::SUCCESS;
    }

    private function tmpPrefix(string $tag): string
    {
        return 'ztmp-'.$tag.'-'.Str::lower(Str::random(10)).'-';
    }

    private function regenerateBrands(bool $dry): void
    {
        $this->info('Бренды…');
        $q = Brand::query()->where('slug', '!=', Brand::PLATFORM_UNKNOWN_SLUG)->orderBy('id');
        if ($dry) {
            foreach ($q->cursor() as $b) {
                $new = BrandCatalogSlug::unique((string) $b->name, $b->id);
                if ($new !== $b->slug) {
                    $this->line("  #{$b->id} «{$b->name}»: {$b->slug} → {$new}");
                }
            }

            return;
        }

        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $tmp = $this->tmpPrefix('b');
        DB::transaction(function () use ($ids, $tmp): void {
            foreach ($ids as $id) {
                Brand::query()->whereKey($id)->update(['slug' => Str::limit($tmp.$id, 255, '')]);
            }
        });

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $b = Brand::query()->find($id);
                if ($b === null) {
                    continue;
                }
                $new = BrandCatalogSlug::unique((string) $b->name, $b->id);
                Brand::query()->whereKey($id)->update(['slug' => $new]);
            }
        });
    }

    private function regenerateCategories(bool $dry): void
    {
        $this->info('Категории…');
        $locked = Category::LOCKED_SLUGS;
        $q = Category::query()->whereNotIn('slug', $locked)->orderBy('id');

        if ($dry) {
            foreach ($q->cursor() as $c) {
                $new = CategoryCatalogSlug::unique((string) $c->name, $c->id);
                if ($new !== $c->slug) {
                    $this->line("  #{$c->id} «{$c->name}»: {$c->slug} → {$new}");
                }
            }

            return;
        }

        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $tmp = $this->tmpPrefix('c');
        DB::transaction(function () use ($ids, $tmp): void {
            foreach ($ids as $id) {
                Category::query()->whereKey($id)->update(['slug' => Str::limit($tmp.$id, 255, '')]);
            }
        });

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $c = Category::query()->find($id);
                if ($c === null) {
                    continue;
                }
                $new = CategoryCatalogSlug::unique((string) $c->name, $c->id);
                Category::query()->whereKey($id)->update(['slug' => $new]);
            }
        });
    }

    private function regenerateProducts(bool $dry, int $chunk): void
    {
        $count = (int) Product::query()->count();
        $this->info("Товары ({$count} шт.)…");

        if ($dry) {
            Product::query()->with('brand')->orderBy('id')->chunkById($chunk, function ($products): void {
                foreach ($products as $p) {
                    $brandName = $p->brand?->name;
                    $new = ProductCatalogSlug::unique(
                        (string) $p->name,
                        $brandName !== null && $brandName !== '' ? (string) $brandName : null,
                        $p->id
                    );
                    if ($new !== $p->slug) {
                        $this->line("  #{$p->id} «{$p->name}»: {$p->slug} → {$new}");
                    }
                }
            });

            return;
        }

        $tmp = $this->tmpPrefix('p');

        $this->comment('  фаза 1: временные slug…');
        Product::query()->orderBy('id')->chunkById($chunk, function ($products) use ($tmp): void {
            DB::transaction(function () use ($products, $tmp): void {
                foreach ($products as $p) {
                    Product::query()->whereKey($p->id)->update(['slug' => Str::limit($tmp.$p->id, 500, '')]);
                }
            });
        });

        $this->comment('  фаза 2: итоговые slug…');
        Product::query()->with('brand')->orderBy('id')->chunkById($chunk, function ($products): void {
            DB::transaction(function () use ($products): void {
                foreach ($products as $p) {
                    $brandName = $p->brand?->name;
                    $new = ProductCatalogSlug::unique(
                        (string) $p->name,
                        $brandName !== null && $brandName !== '' ? (string) $brandName : null,
                        $p->id
                    );
                    Product::query()->whereKey($p->id)->update(['slug' => $new]);
                }
            });
        });
    }

    private function regeneratePages(bool $dry): void
    {
        $this->info('Страницы сайта…');
        $locked = Page::LOCKED_SLUGS;
        $q = Page::query()->whereNotIn('slug', $locked)->orderBy('id');

        if ($dry) {
            foreach ($q->cursor() as $p) {
                $new = PageCatalogSlug::unique((string) $p->title, $p->id);
                if ($new !== $p->slug) {
                    $this->line("  #{$p->id} «{$p->title}»: {$p->slug} → {$new}");
                }
            }

            return;
        }

        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $tmp = $this->tmpPrefix('pg');
        DB::transaction(function () use ($ids, $tmp): void {
            foreach ($ids as $id) {
                Page::query()->whereKey($id)->update(['slug' => Str::limit($tmp.$id, 255, '')]);
            }
        });

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $p = Page::query()->find($id);
                if ($p === null) {
                    continue;
                }
                $new = PageCatalogSlug::unique((string) $p->title, $p->id);
                Page::query()->whereKey($id)->update(['slug' => $new]);
            }
        });
    }

    private function regenerateSellers(bool $dry): void
    {
        $this->info('Продавцы (компании)…');
        $q = Seller::query()->orderBy('id');

        if ($dry) {
            foreach ($q->cursor() as $s) {
                $new = MarketplaceSellerSlug::unique((string) $s->name, $s->id);
                if ($new !== $s->slug) {
                    $this->line("  #{$s->id} «{$s->name}»: {$s->slug} → {$new}");
                }
            }

            return;
        }

        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $tmp = $this->tmpPrefix('s');
        DB::transaction(function () use ($ids, $tmp): void {
            foreach ($ids as $id) {
                Seller::query()->whereKey($id)->update(['slug' => Str::limit($tmp.$id, 255, '')]);
            }
        });

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $s = Seller::query()->find($id);
                if ($s === null) {
                    continue;
                }
                $new = MarketplaceSellerSlug::unique((string) $s->name, $s->id);
                Seller::query()->whereKey($id)->update(['slug' => $new]);
            }
        });
    }
}
