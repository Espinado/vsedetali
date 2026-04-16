<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Дозапись строк в TSV для ручной проверки конфликтов «название товара ↔ категория каталога».
 */
final class CatalogStorefrontCategoryModerationLogger
{
    private const HEADER = "ts\treason\tproduct_id\tsku\tproduct_name\tcategory_main\tcategory_sub\tcategory_assigned_path\tsource\n";

    /**
     * @param  'skipped_assignment'|'existing_product_scan'  $source
     */
    public static function log(
        string $reason,
        int $productId,
        string $sku,
        string $productName,
        string $categoryMain,
        string $categorySub,
        string $categoryAssignedPath,
        string $source,
    ): void {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (! is_file($path) || filesize($path) === 0) {
            File::put($path, self::HEADER);
        }

        $row = implode("\t", [
            now()->toIso8601String(),
            $reason,
            (string) $productId,
            self::escapeTsvCell($sku),
            self::escapeTsvCell($productName),
            self::escapeTsvCell($categoryMain),
            self::escapeTsvCell($categorySub),
            self::escapeTsvCell($categoryAssignedPath),
            $source,
        ])."\n";

        File::append($path, $row, true);
    }

    public static function path(): string
    {
        $configured = config('storefront.category_conflict_moderation_path');
        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return storage_path('app/moderation/catalog-storefront-category-conflicts.tsv');
    }

    private static function escapeTsvCell(string $s): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], [' ', ' ', ' '], $s);

        return str_replace("\t", ' ', $s);
    }
}
