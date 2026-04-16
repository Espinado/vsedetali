<?php

namespace App\Support;

use App\Services\RemainsStockCsvImportService;

/**
 * Нормализация строки JSONL (*-oem-bundles.jsonl) к числовому виду CSV «Остатки» (индексы как в {@see RemainsStockCsvImportService}).
 */
final class RemainsOemBundleJsonlRow
{
    /**
     * @param  list<string>  $csvRow
     * @return array<int, string>
     */
    public static function padCsvRow(array $csvRow, int $size = 16): array
    {
        $row = array_values($csvRow);
        if (count($row) < $size) {
            $row = array_pad($row, $size, '');
        }

        return array_slice($row, 0, $size);
    }

    /**
     * Если в JSONL нет поля `csv_row`, пробуем собрать поля по типовым заголовкам 1С (без учёта регистра).
     *
     * @param  array<string, string>  $csvAssoc
     * @return array<int, string>
     */
    public static function numericRowFromCsvAssoc(array $csvAssoc): array
    {
        $norm = [];
        foreach ($csvAssoc as $k => $v) {
            $norm[mb_strtolower(trim((string) $k), 'UTF-8')] = trim((string) $v);
        }

        $pick = static function (array $aliases) use ($norm): string {
            foreach ($aliases as $a) {
                $lk = mb_strtolower(trim($a), 'UTF-8');
                if (isset($norm[$lk]) && $norm[$lk] !== '') {
                    return $norm[$lk];
                }
            }
            foreach ($norm as $key => $val) {
                foreach ($aliases as $a) {
                    if (mb_stripos($key, mb_strtolower($a, 'UTF-8'), 0, 'UTF-8') !== false) {
                        return $val;
                    }
                }
            }

            return '';
        };

        $row = array_fill(0, 16, '');
        $row[1] = $pick(['код', 'code']);
        $row[2] = $pick(['артикул', 'sku', 'номер']);
        $row[3] = $pick(['наименование', 'название', 'name', 'товар']);
        $row[5] = $pick(['доступно']);
        $row[6] = $pick(['резерв']);
        $row[8] = $pick(['остаток']);
        $row[9] = $pick(['себестоимость', 'cost']);
        $row[11] = $pick(['цена', 'price', 'розничная']);
        $row[13] = $pick(['дней на складе', 'дней', 'days']);

        return $row;
    }
}
