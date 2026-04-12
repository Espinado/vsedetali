<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalogSlug;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * XLSX: жёлтый фон строки (или B заполнен, C пуст) = категория в столбце B; далее строки с артикулом в C.
 * Столбец A: первая лексема — марка, остаток строки — модель (например Geely | Coolray SX11).
 */
class GeelyVesnaSpringExcelImportService
{
    /** @var array{rows: int, skipped: int, imported: int, category_rows: int, created_products: int, updated_products: int, created_vehicles: int, attached_vehicles: int, created_categories: int} */
    public function import(string $absolutePath, bool $dryRun = false): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new \RuntimeException(
                'Не найден пакет PhpSpreadsheet. В каталоге проекта выполните: composer update'
            );
        }

        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("Файл недоступен: {$absolutePath}");
        }

        $stats = [
            'rows' => 0,
            'skipped' => 0,
            'imported' => 0,
            'category_rows' => 0,
            'created_products' => 0,
            'updated_products' => 0,
            'created_vehicles' => 0,
            'attached_vehicles' => 0,
            'created_categories' => 0,
        ];

        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheet((int) config('geely_vesna_spring_excel_import.sheet_index', 0));
        $firstRow = max(1, (int) config('geely_vesna_spring_excel_import.first_data_row', 1));
        $highestRow = (int) $sheet->getHighestDataRow();

        $warehouse = null;
        $parentCategory = null;
        if (! $dryRun) {
            $warehouse = Warehouse::query()->where('is_default', true)->first()
                ?? Warehouse::query()->where('is_active', true)->first()
                ?? Warehouse::query()->first();

            if (! $warehouse) {
                $warehouse = Warehouse::query()->create([
                    'name' => 'Импорт',
                    'code' => 'IMPORT',
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            $pc = (array) config('geely_vesna_spring_excel_import.parent_category', []);
            $parentCategory = Category::query()->firstOrCreate(
                ['slug' => (string) ($pc['slug'] ?? 'import-geely-vesna-spring')],
                [
                    'name' => (string) ($pc['name'] ?? 'Geely Весна (импорт)'),
                    'parent_id' => null,
                    'sort' => 9998,
                    'is_active' => true,
                ]
            );
        }

        $currentCategoryName = '';
        $currentLeafCategory = null;
        $lastMake = null;
        $lastModel = null;

        for ($row = $firstRow; $row <= $highestRow; $row++) {
            $stats['rows']++;

            $colA = $this->stringCell($sheet, 'A', $row);
            $colB = $this->stringCell($sheet, 'B', $row);
            $colC = $this->stringCell($sheet, 'C', $row);
            $colD = $this->stringCell($sheet, 'D', $row);

            if ($this->rowIsCategoryHeader($sheet, $row, $colB, $colC, $colD)) {
                $stats['category_rows']++;
                $name = trim($colB);
                if ($name === '' && ($this->cellFillIsYellowish($sheet, 'A', $row) || $this->cellFillIsYellowish($sheet, 'B', $row))) {
                    $name = trim($colA);
                }
                if ($name === '') {
                    $stats['skipped']++;

                    continue;
                }

                $currentCategoryName = $name;
                if (! $dryRun && $parentCategory !== null) {
                    $currentLeafCategory = $this->firstOrCreateLeafCategory($parentCategory, $name, $stats);
                }

                continue;
            }

            if ($colC === '') {
                $stats['skipped']++;

                continue;
            }

            if ($currentCategoryName === '') {
                $stats['skipped']++;

                continue;
            }

            if ($colA !== '') {
                [$mk, $mdl] = $this->parseMakeModelFromColumnA($colA);
                if ($mk !== null) {
                    $lastMake = $mk;
                }
                if ($mdl !== null) {
                    $lastModel = $mdl;
                }
            }

            if ($lastMake === null || $lastModel === null) {
                $stats['skipped']++;

                continue;
            }

            $article = $colC;
            $sku = mb_strlen($article) <= 100
                ? $article
                : (mb_substr($article, 0, 90).'_'.substr(md5($article), 0, 8));
            $quantity = $this->parseQuantity($colD);
            $productName = Str::limit(trim($currentCategoryName.' '.$article), 500, '');

            if ($dryRun) {
                $stats['imported']++;

                continue;
            }

            if ($currentLeafCategory === null) {
                $stats['skipped']++;

                continue;
            }

            DB::transaction(function () use (
                &$stats,
                $sku,
                $productName,
                $lastMake,
                $lastModel,
                $quantity,
                $warehouse,
                $currentLeafCategory,
                $article
            ) {
                $brand = Brand::query()->firstOrCreate(
                    ['slug' => Str::slug($lastMake) ?: Str::slug($lastMake.'-make')],
                    ['name' => $lastMake, 'is_active' => true]
                );

                $vehicle = Vehicle::query()->firstOrCreate(
                    [
                        'make' => $lastMake,
                        'model' => $lastModel,
                        'generation' => null,
                    ],
                    [
                        'year_from' => null,
                        'year_to' => null,
                        'engine' => null,
                        'body_type' => null,
                    ]
                );

                if ($vehicle->wasRecentlyCreated) {
                    $stats['created_vehicles']++;
                }

                $product = Product::query()->where('sku', $sku)->first();

                if ($product === null) {
                    $product = new Product;
                    $product->sku = $sku;
                    $product->category_id = $currentLeafCategory->id;
                    $product->brand_id = $brand->id;
                    $product->price = 0;
                    $product->is_active = true;
                    $product->type = 'part';
                    $stats['created_products']++;
                } else {
                    $stats['updated_products']++;
                    if ($product->category_id === null) {
                        $product->category_id = $currentLeafCategory->id;
                    }
                }

                $product->name = $productName;
                $product->brand_id = $brand->id;
                $product->slug = ProductCatalogSlug::unique(
                    $productName,
                    $brand->name,
                    $product->exists ? $product->id : null
                );
                $product->save();

                $oemForPivot = Str::limit(explode('/', $article)[0], 100, '');

                if (! $product->vehicles()->where('vehicles.id', $vehicle->id)->exists()) {
                    $product->vehicles()->attach($vehicle->id, ['oem_number' => $oemForPivot ?: null]);
                    $stats['attached_vehicles']++;
                }

                Stock::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => $quantity,
                        'reserved_quantity' => 0,
                    ]
                );
            });

            $stats['imported']++;
        }

        return $stats;
    }

    protected function firstOrCreateLeafCategory(Category $parent, string $name, array &$stats): Category
    {
        $name = Str::limit(trim($name), 255, '');

        $existing = Category::query()
            ->where('parent_id', $parent->id)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $baseSlug = 'gv26-'.Str::slug($name);
        if ($baseSlug === 'gv26-' || $baseSlug === 'gv26') {
            $baseSlug = 'gv26-'.substr(sha1($name), 0, 12);
        }

        $slug = $baseSlug;
        $n = 0;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.(++$n);
        }

        $cat = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => $name,
            'slug' => Str::limit($slug, 255, ''),
            'is_active' => true,
            'sort' => 0,
        ]);
        $stats['created_categories']++;

        return $cat;
    }

    /**
     * @return array{0: ?string, 1: ?string} make, model
     */
    protected function parseMakeModelFromColumnA(string $colA): array
    {
        $colA = trim(preg_replace('/\s+/u', ' ', $colA) ?? '');
        if ($colA === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/u', $colA, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return [null, null];
        }

        if (count($parts) >= 2) {
            $make = VehicleLabelNormalizer::title($parts[0]);
            $model = VehicleLabelNormalizer::title(trim(implode(' ', array_slice($parts, 1))));

            return [$make, $model];
        }

        return [null, VehicleLabelNormalizer::title($parts[0])];
    }

    protected function parseQuantity(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }

        return max(0, (int) round((float) str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', $raw) ?? '0')));
    }

    protected function stringCell(Worksheet $sheet, string $colLetter, int $row): string
    {
        $coord = $colLetter.$row;
        $cell = $sheet->getCell($coord);
        $v = $cell->getValue();
        if (is_numeric($v)) {
            return trim((string) $v);
        }
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return trim($v);
        }

        return trim((string) $cell->getFormattedValue());
    }

    protected function rowIsCategoryHeader(Worksheet $sheet, int $row, string $colB, string $colC, string $colD): bool
    {
        if ($this->cellFillIsYellowish($sheet, 'B', $row)) {
            return true;
        }

        return $colB !== '' && $colC === '' && $colD === '';
    }

    protected function cellFillIsYellowish(Worksheet $sheet, string $colLetter, int $row): bool
    {
        try {
            $coord = $colLetter.$row;
            $style = $sheet->getStyle($coord);
            $fill = $style->getFill();
            $type = $fill->getFillType();
            if ($type !== Fill::FILL_SOLID) {
                return false;
            }

            $color = $fill->getStartColor();
            $rgb = $color->getRGB();
            if ($rgb === null || $rgb === '') {
                $argb = $color->getARGB();
                if (is_string($argb) && strlen($argb) >= 6) {
                    $rgb = strlen($argb) === 8 ? substr($argb, 2) : $argb;
                }
            }

            $rgb = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $rgb) ?? '');
            if (strlen($rgb) !== 6) {
                return false;
            }

            $r = hexdec(substr($rgb, 0, 2));
            $g = hexdec(substr($rgb, 2, 2));
            $b = hexdec(substr($rgb, 4, 2));

            return $r >= 180 && $g >= 180 && $b <= 200;
        } catch (\Throwable) {
            return false;
        }
    }

}
