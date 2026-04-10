<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class SellerListingVehicleCompatibilities
{
    /**
     * @return array<int>
     */
    public static function parseYears(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $y) {
                $y = (int) $y;
                if ($y >= 1900 && $y <= 2100) {
                    $out[] = $y;
                }
            }

            return array_values(array_unique($out));
        }
        if (is_string($raw)) {
            $parts = preg_split('/[\s,;]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
            $out = [];
            foreach ($parts as $p) {
                $p = trim($p);
                if (preg_match('/^(\d{4})\s*[-–\x{2014}]\s*(\d{4})$/u', $p, $m)) {
                    $a = (int) $m[1];
                    $b = (int) $m[2];
                    if ($a > $b) {
                        [$a, $b] = [$b, $a];
                    }
                    for ($y = $a; $y <= $b; $y++) {
                        if ($y >= 1900 && $y <= 2100) {
                            $out[] = $y;
                        }
                    }

                    continue;
                }
                if (preg_match('/^\d{4}$/', $p)) {
                    $y = (int) $p;
                    if ($y >= 1900 && $y <= 2100) {
                        $out[] = $y;
                    }
                }
            }

            return array_values(array_unique($out));
        }

        return [];
    }

    /**
     * @return list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>}>
     */
    public static function normalizeRepeaterRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized[] = [
                'vehicle_make' => isset($row['vehicle_make']) ? trim((string) $row['vehicle_make']) : '',
                'vehicle_model' => isset($row['vehicle_model']) ? trim((string) $row['vehicle_model']) : '',
                'compatibility_years' => self::parseYears($row['compatibility_years'] ?? null),
            ];
        }

        return $normalized;
    }

    public static function formatVehicleYearsForInput(Vehicle $v): string
    {
        $from = $v->year_from;
        $to = $v->year_to;
        if ($from === null && $to === null) {
            return '';
        }
        if ($from !== null && $to !== null) {
            return $from === $to ? (string) $from : "{$from}–{$to}";
        }

        return (string) ($from ?? $to);
    }

    /**
     * @param  list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>}>  $rows
     * @return Collection<int, int>
     */
    public static function collectVehicleIds(array $rows): Collection
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'vehicle_compatibilities' => 'Добавьте хотя бы одну строку совместимости (марка, модель, годы).',
            ]);
        }

        $vehicleIds = collect();
        foreach ($rows as $index => $row) {
            $make = $row['vehicle_make'];
            $model = $row['vehicle_model'];
            $years = array_values(array_unique(array_map('intval', $row['compatibility_years'] ?? [])));
            $path = "vehicle_compatibilities.{$index}.compatibility_years";

            if ($make === '' || $model === '') {
                throw ValidationException::withMessages([
                    "vehicle_compatibilities.{$index}.vehicle_make" => 'Укажите марку и модель в этой строке.',
                ]);
            }
            if ($years === []) {
                throw ValidationException::withMessages([
                    $path => 'Укажите хотя бы один год выпуска для этой пары марка/модель.',
                ]);
            }

            $ids = Vehicle::idsMatchingMakeModelYears($make, $model, $years);
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages([
                    $path => 'Для выбранных марки, модели и годов в этой строке нет записей в справочнике авто. Обратитесь к администрации площадки.',
                ]);
            }
            $vehicleIds = $vehicleIds->merge($ids);
        }

        $vehicleIds = $vehicleIds->unique()->values();
        if ($vehicleIds->isEmpty()) {
            throw ValidationException::withMessages([
                'vehicle_compatibilities' => 'Не удалось сопоставить ни одну строку совместимости со справочником авто.',
            ]);
        }

        return $vehicleIds;
    }

    /**
     * Без `multiple()` Filament может отдать один путь строкой; при `multiple()` — массив строк.
     * Порядок файлов сохраняем (дубликаты путей убираем).
     *
     * @return list<string>
     */
    public static function normalizeListingImageUpload(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $s = trim($raw);

            return $s === '' ? [] : [$s];
        }
        if (! is_array($raw)) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = trim($item);
            if ($t === '' || isset($seen[$t])) {
                continue;
            }
            $seen[$t] = true;
            $out[] = $t;
        }

        return $out;
    }
}
