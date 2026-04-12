<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class SellerListingVehicleCompatibilities
{
    /** Макс. разница «последний год − первый год» в одном диапазоне (включительно лет на 1 больше). */
    private const MANUAL_YEAR_RANGE_MAX_DIFF = 60;

    /** Макс. число различных лет после разворота диапазонов в одной строке ручного ввода. */
    private const MANUAL_YEAR_LIST_MAX_DISTINCT = 100;

    /**
     * Подсказка у поля ручного ввода годов (админка / кабинет продавца).
     */
    public static function freeformCompatibilityYearsFieldHint(): string
    {
        return 'Ввод только календарных годов (1900–2100). Один год — четыре цифры (2018). '
            .'Несколько лет — через запятую, пробел или точку с запятой (2018, 2019, 2020). '
            .'Диапазон — два четырёхзначных года через дефис, длинное тире или минус Unicode (2015-2020, 2015–2020); пробелы вокруг тире допускаются. '
            .'Поколение кузова (F30, Mk7 и т.п.) сюда не вводите — оно задаётся в справочнике «Автомобили»; если показан блок «Записи из справочника», выберите там нужную строку. '
            .'Буквы, слэши, скобки и прочие символы недопустимы; проверка при сохранении строгая.';
    }

    /**
     * Нормализация строки ручного ввода: неразрывный пробел, табы, пробелы вокруг тире в диапазоне.
     */
    private static function normalizeManualYearsFreeformString(string $trimmed): string
    {
        $s = str_replace(["\xC2\xA0", "\t", "\r", "\n"], [' ', ' ', ' ', ' '], $trimmed);
        $s = preg_replace_callback(
            '/\d{4}\s*[-–\x{2014}\x{2212}]\s*\d{4}/u',
            static fn (array $m): string => preg_replace('/\s+/u', '', $m[0]),
            $s
        ) ?? $s;
        $s = trim(preg_replace('/ +/u', ' ', $s) ?? '');

        return $s;
    }

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
            $normalized = self::normalizeManualYearsFreeformString(trim($raw));
            $parts = preg_split('/[\s,;]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
            $out = [];
            foreach ($parts as $p) {
                $p = trim($p);
                if (preg_match('/^(\d{4})\s*[-–\x{2014}\x{2212}]\s*(\d{4})$/u', $p, $m)) {
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
        $normalized = self::normalizeManualYearsFreeformString($trimmed);
        if ($normalized === '') {
            return;
        }

        if (preg_match('/[^\d\s,;\-–\x{2014}\x{2212}]/u', $normalized)) {
            throw ValidationException::withMessages([
                $path => 'Допустимы только цифры, пробел, запятая, «;» и тире между двумя годами. Поколение (F30, Mk7 и т.д.) вводите не здесь — см. подсказку под полем.',
            ]);
        }

        $parts = preg_split('/[\s,;]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^(\d{4})\s*[-–\x{2014}\x{2212}]\s*(\d{4})$/u', $p, $m)) {
                $a = (int) $m[1];
                $b = (int) $m[2];
                if ($a > $b) {
                    [$a, $b] = [$b, $a];
                }
                if ($a < 1900 || $a > 2100 || $b < 1900 || $b > 2100) {
                    throw ValidationException::withMessages([
                        $path => 'Год в «'.$p.'» вне допустимого диапазона 1900–2100.',
                    ]);
                }
                if ($b - $a > self::MANUAL_YEAR_RANGE_MAX_DIFF) {
                    throw ValidationException::withMessages([
                        $path => 'Слишком широкий диапазон «'.$p.'». Проверьте годы или разбейте на несколько диапазонов.',
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
            if (is_string($rawYears) && trim($rawYears) !== '' && count($parsedYears) > self::MANUAL_YEAR_LIST_MAX_DISTINCT) {
                throw ValidationException::withMessages([
                    "vehicle_compatibilities.{$index}.compatibility_years" => 'Слишком много лет в одной строке. Сократите диапазоны или разбейте совместимость на несколько строк (марка/модель).',
                ]);
            }
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

            $pickedIds = self::parseVehicleRowIds($row['vehicle_row_ids'] ?? null);
            if (Vehicle::compatibilityPickerRowsWithDefinedYears($make, $model)->isEmpty()) {
                $pickedIds = [];
            }

            $normalized[] = [
                'vehicle_make' => $make,
                'vehicle_model' => $model,
                'compatibility_years' => $parsedYears,
                'vehicle_row_ids' => $pickedIds,
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
            $make = $row['vehicle_make'] ?? '';
            $model = $row['vehicle_model'] ?? '';
            $picksAllowed = Vehicle::compatibilityPickerRowsWithDefinedYears($make, $model)->isNotEmpty();
            $effectivePicks = $picksAllowed ? $picks : [];
            if ($years === [] && $effectivePicks === []) {
                throw ValidationException::withMessages([
                    "vehicle_compatibilities.{$index}.compatibility_years" => 'Укажите годы выпуска или выберите записи из справочника.',
                    "vehicle_compatibilities.{$index}.vehicle_row_ids" => 'Укажите годы выпуска или выберите записи из справочника.',
                ]);
            }
        }
    }

    /**
     * @param  list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>, vehicle_row_ids: array<int>}>  $rows
     * @return array<int, array{compat_year_from: ?int, compat_year_to: ?int}>
     */
    public static function collectVehiclePivotSync(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'vehicle_compatibilities' => 'Добавьте хотя бы одну строку совместимости (марка, модель, годы или записи из справочника).',
            ]);
        }

        $allIds = [];
        /** @var array<int, 'full'|list<int>> $constraints */
        $constraints = [];

        $mergeFull = function (int $id) use (&$constraints): void {
            $constraints[$id] = 'full';
        };

        $mergeYears = function (int $id, array $subset) use (&$constraints): void {
            if ($subset === []) {
                return;
            }
            if (($constraints[$id] ?? null) === 'full') {
                return;
            }
            $prev = $constraints[$id] ?? [];
            $constraints[$id] = array_values(array_unique(array_merge($prev, $subset)));
        };

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

            $catalogOpts = Vehicle::yearOptionsForMakeAndModel($make, $model);
            if (Vehicle::compatibilityPickerRowsWithDefinedYears($make, $model)->isEmpty()) {
                $pickedIds = [];
            }

            if ($years === [] && $pickedIds === []) {
                throw ValidationException::withMessages([
                    $pathYears => 'Укажите годы выпуска или выберите записи из справочника.',
                    $pathPicks => 'Укажите годы выпуска или выберите записи из справочника.',
                ]);
            }

            if ($pickedIds !== [] && $catalogOpts !== [] && $years === []) {
                throw ValidationException::withMessages([
                    $pathYears => 'Выбраны записи из справочника и в каталоге для этой пары заданы годы — укажите годы применимости (список ниже).',
                ]);
            }

            $idsForRow = collect();
            $picked = collect();

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
                $idsForRow = $idsForRow->merge($pickedIds);
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

            foreach ($idsForRow->unique()->values() as $vid) {
                $allIds[(int) $vid] = true;
            }

            foreach ($idsForRow->unique()->values() as $vid) {
                $vid = (int) $vid;
                $v = $picked->get($vid) ?? Vehicle::query()->find($vid);
                if ($v === null) {
                    continue;
                }

                if ($years === []) {
                    $mergeFull($vid);

                    continue;
                }

                $subset = $v->intersectYearListWithBounds($years);
                if ($subset === []) {
                    throw ValidationException::withMessages([
                        $pathYears => 'Указанные годы не пересекаются с записью «'.$v->adminCompatibilityPickerLabel().'».',
                    ]);
                }
                $mergeYears($vid, $subset);
            }
        }

        $sync = [];
        foreach (array_keys($allIds) as $id) {
            $id = (int) $id;
            $c = $constraints[$id] ?? null;
            if ($c === 'full' || $c === null) {
                $sync[$id] = ['compat_year_from' => null, 'compat_year_to' => null];
            } else {
                /** @var list<int> $c */
                $sync[$id] = [
                    'compat_year_from' => min($c),
                    'compat_year_to' => max($c),
                ];
            }
        }

        if ($sync === []) {
            throw ValidationException::withMessages([
                'vehicle_compatibilities' => 'Не удалось сопоставить ни одну строку совместимости со справочником авто.',
            ]);
        }

        return $sync;
    }

    /**
     * @param  list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int>, vehicle_row_ids: array<int>}>  $rows
     * @return Collection<int, int>
     */
    public static function collectVehicleIds(array $rows): Collection
    {
        return collect(array_keys(self::collectVehiclePivotSync($rows)))->values();
    }

    /**
     * Значение полей «годы» в repeater при редактировании (с учётом pivot).
     *
     * @param  \Illuminate\Database\Eloquent\Model|\stdClass|object|null  $pivot
     */
    public static function formStateForVehicleCompatibilityYears(Vehicle $vehicle, mixed $pivot): array|string|null
    {
        $cf = data_get($pivot, 'compat_year_from');
        $ct = data_get($pivot, 'compat_year_to');
        if ($cf !== null && $cf !== '' && $ct !== null && $ct !== '') {
            $cf = (int) $cf;
            $ct = (int) $ct;
            if ($cf > $ct) {
                [$cf, $ct] = [$ct, $cf];
            }
            $opts = Vehicle::yearOptionsForMakeAndModel($vehicle->make, $vehicle->model);
            $range = range($cf, $ct);
            if ($opts !== []) {
                return array_values(array_filter($range, static fn (int $y): bool => isset($opts[$y])));
            }

            return implode(', ', $range);
        }

        return $vehicle->compatibilityYearsFormState();
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
