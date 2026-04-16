<?php

namespace App\Services;

use App\Models\Brand;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Support\Str;

/**
 * Секции CSV «Остатки» (Марки/…, Производители/…, DSLK, …) → контекст импорта одной строки товара.
 */
final class RemainsStockCsvSectionContextParser
{
    /**
     * @return array{standalone: bool, part_brand_id: ?int, vehicles: list<array{make: string, model: ?string}>}
     */
    public function parse(string $code, bool $dryRun): array
    {
        $code = trim($code);
        $standalone = config('remains_stock_import.standalone_section_labels', []);

        foreach ($standalone as $label) {
            if ($code === $label) {
                return [
                    'standalone' => true,
                    'part_brand_id' => null,
                    'vehicles' => [],
                ];
            }
        }

        if ($code === 'DSLK') {
            $cfg = config('remains_stock_import.dslk_brand', ['name' => 'DI-SOLIK', 'slug' => 'di-solik']);
            $brand = null;
            if (! $dryRun) {
                $brand = Brand::query()->firstOrCreate(
                    ['slug' => $cfg['slug']],
                    ['name' => $cfg['name'], 'is_active' => true]
                );
            }

            return [
                'standalone' => false,
                'part_brand_id' => $brand?->id,
                'vehicles' => [],
            ];
        }

        if (str_starts_with($code, 'Производители/')) {
            $raw = trim(substr($code, strlen('Производители/')));
            $brandName = $raw !== '' ? $raw : 'Unknown';
            $brand = null;
            if (! $dryRun) {
                $slug = Str::slug($brandName) ?: 'brand-'.Str::lower(Str::random(6));
                $brand = Brand::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => Str::limit($brandName, 255, ''), 'is_active' => true]
                );
            }

            return [
                'standalone' => false,
                'part_brand_id' => $brand?->id,
                'vehicles' => [],
            ];
        }

        if (str_starts_with($code, 'Марки/')) {
            $path = trim(substr($code, strlen('Марки/')));
            if ($path === '') {
                return [
                    'standalone' => true,
                    'part_brand_id' => null,
                    'vehicles' => [],
                ];
            }

            $segments = array_values(array_filter(array_map('trim', explode('/', $path)), fn ($s) => $s !== ''));

            $multi = config('remains_stock_import.multi_make_segments', []);

            if (count($segments) === 1 && isset($multi[$segments[0]])) {
                $vehicles = [];
                foreach ($multi[$segments[0]] as $makeName) {
                    $vehicles[] = [
                        'make' => VehicleLabelNormalizer::title($makeName),
                        'model' => null,
                    ];
                }

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => $vehicles,
                ];
            }

            if (count($segments) >= 2) {
                $make = VehicleLabelNormalizer::title($segments[0]);
                $rest = array_slice($segments, 1);
                $modelLabel = VehicleLabelNormalizer::title(implode(' ', $rest));

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => [['make' => $make, 'model' => $modelLabel]],
                ];
            }

            if (count($segments) === 1) {
                $make = VehicleLabelNormalizer::title($segments[0]);

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => [['make' => $make, 'model' => null]],
                ];
            }
        }

        $parts = preg_split('/\s+/u', $code, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 1) {
            $make = VehicleLabelNormalizer::title($parts[0]);
            $model = count($parts) >= 2
                ? VehicleLabelNormalizer::title(implode(' ', array_slice($parts, 1)))
                : null;

            return [
                'standalone' => false,
                'part_brand_id' => null,
                'vehicles' => [['make' => $make, 'model' => $model]],
            ];
        }

        return [
            'standalone' => true,
            'part_brand_id' => null,
            'vehicles' => [],
        ];
    }
}
