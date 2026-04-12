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
     * Строгая проверка свободной строки годов (например, из импорта; в форме — мультивыбор без ввода текста).
     *
     * @throws ValidationException
     */
    public static function assertYearsFreeformStringStrictOrThrow(string $trimmed, int $rowIndex): void
    {
        if ($trimmed === '') {
            return;
        }

        $path = "vehicle_compatibilities.{$rowIndex}.compatibility_years";
        $parts = preg_split('/[\s,;]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^(\d{4})\s*[-–\x{2014}]\s*(\d{4})$/u', $p, $m)) {
                $a = (int) $m[1];
                $b = (int) $m[2];
                if ($a < 1900 || $a > 2100 || $b < 1900 || $b > 2100) {
                    throw ValidationException::withMessages([
                        $path => 'Год в «'.$p.'» вне допустимого диапазона 1900–2100.',
                    ]);
                }

                continue;
            }
            if (preg_match('/^\d{4}$/', $p)) {
                $y = (int) $p;
                if ($y < 1900 || $y > 2100) {
                    throw ValidationException::withMessages([
                        $path => 'Год «'.$p.'» вне допустимого диапазона 1900–2100.',
                    ]);
                }

                continue;
            }

            throw ValidationException::withMessages([
                $path => 'Недопустимый фрагмент «'.$p.'». Укажите год четырьмя цифрами или диапазон вида 2015-2020.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public static function parseVehicleRowIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $id = (int) $raw;

            return $id > 0 ? [$id] : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>, vehicle_row_ids: array<int>}>
     */
    public static function normalizeRepeaterRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $rawYears = $row['compatibility_years'] ?? null;
            if (is_string($rawYears)) {
                self::assertYearsFreeformStringStrictOrThrow(trim($rawYears), (int) $index);
            }

            $make = isset($row['vehicle_make']) ? trim((string) $row['vehicle_make']) : '';
            $model = isset($row['vehicle_model']) ? trim((string) $row['vehicle_model']) : '';
            $parsedYears = self::parseYears($rawYears);
            if ($parsedYears !== [] && $make !== '' && $model !== '') {
                $allowed = Vehicle::yearAllowedIntsForCompatibilityPicker($make, $model);
                foreach ($parsedYears as $y) {
                    if (! in_array($y, $allowed, true)) {
                        throw ValidationException::withMessages([
                            "vehicle_compatibilities.{$index}.compatibility_years" => 'Один или несколько выбранных лет недопустимы для этой марки и модели.',
                        ]);
                    }
                }
            }

            $normalized[] = [
                'vehicle_make' => $make,
                'vehicle_model' => $model,
                'compatibility_years' => $parsedYears,
                'vehicle_row_ids' => self::parseVehicleRowIds($row['vehicle_row_ids'] ?? null),
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
     * После {@see normalizeRepeaterRows}: в каждой строке должны быть либо годы, либо выбранные id, либо оба.
     *
     * @param  list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>, vehicle_row_ids: array<int>}>  $rows
     */
    public static function assertSellerRowsHavePickOrYears(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $years = $row['compatibility_years'] ?? [];
            $picks = $row['vehicle_row_ids'] ?? [];
            if ($years === [] && $picks === []) {
                throw ValidationException::withMessages([
                    "vehicle_compatibilities.{$index}.compatibility_years" => 'Укажите годы выпуска или выберите записи из справочника.',
                    "vehicle_compatibilities.{$index}.vehicle_row_ids" => 'Укажите годы выпуска или выберите записи из справочника.',
                ]);
            }
        }
    }

    /**
     * @param  list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>, vehicle_row_ids: array<int>}>  $rows
     * @return Collection<int, int>
     */
    public static function collectVehicleIds(array $rows): Collection
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'vehicle_compatibilities' => 'Добавьте хотя бы одну строку совместимости (марка, модель, годы или записи из справочника).',
            ]);
        }

        $vehicleIds = collect();
        foreach ($rows as $index => $row) {
            $make = $row['vehicle_make'];
            $model = $row['vehicle_model'];
            $years = array_values(array_unique(array_map('intval', $row['compatibility_years'] ?? [])));
            $pickedIds = array_values(array_unique(array_map('intval', $row['vehicle_row_ids'] ?? [])));
            $pathYears = "vehicle_compatibilities.{$index}.compatibility_years";
            $pathPicks = "vehicle_compatibilities.{$index}.vehicle_row_ids";

            if ($make === '' || $model === '') {
                throw ValidationException::withMessages([
                    "vehicle_compatibilities.{$index}.vehicle_make" => 'Укажите марку и модель в этой строке.',
                ]);
            }

            if ($years === [] && $pickedIds === []) {
                throw ValidationException::withMessages([
                    $pathYears => 'Укажите годы выпуска или выберите записи из справочника.',
                    $pathPicks => 'Укажите годы выпуска или выберите записи из справочника.',
                ]);
            }

            $idsForRow = collect();

            if ($pickedIds !== []) {
                $picked = Vehicle::query()->whereIn('id', $pickedIds)->get()->keyBy('id');
                if ($picked->count() !== count($pickedIds)) {
                    throw ValidationException::withMessages([
                        $pathPicks => 'Одна или несколько выбранных записей справочника не найдены.',
                    ]);
                }
                $makeLower = mb_strtolower($make);
                $modelLower = mb_strtolower($model);
                foreach ($picked as $v) {
                    if (mb_strtolower(trim((string) $v->make)) !== $makeLower
                        || mb_strtolower(trim((string) $v->model)) !== $modelLower) {
                        throw ValidationException::withMessages([
                            $pathPicks => 'Выбранные записи не соответствуют марке и модели в этой строке.',
                        ]);
                    }
                }
                $idsForRow = $idsForRow->merge(collect($pickedIds));
            }

            if ($years !== []) {
                $allowed = Vehicle::yearAllowedIntsForCompatibilityPicker($make, $model);
                foreach ($years as $y) {
                    if (! in_array($y, $allowed, true)) {
                        throw ValidationException::withMessages([
                            $pathYears => 'Один или несколько выбранных лет недопустимы для этой марки и модели.',
                        ]);
                    }
                }

                $fromYears = Vehicle::idsMatchingMakeModelYears($make, $model, $years);
                if ($fromYears->isEmpty()) {
                    throw ValidationException::withMessages([
                        $pathYears => 'Для выбранных марки, модели и годов в этой строке нет записей в справочнике авто. Обратитесь к администрации площадки.',
                    ]);
                }
                $idsForRow = $idsForRow->merge($fromYears);
            }

            $vehicleIds = $vehicleIds->merge($idsForRow->unique()->values());
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
