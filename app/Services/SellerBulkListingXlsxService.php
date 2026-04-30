<?php

namespace App\Services;

use App\Authorization\StaffPermission;
use App\Models\SellerStaff;
use App\Models\Warehouse;
use App\Support\SellerListingVehicleCompatibilities;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SellerBulkListingXlsxService
{
    public const SHEET_DATA = 'Позиции';

    public const SHEET_HELP = 'Инструкция';

    public const COL_VALIDATION_STATUS = 'validation_status';

    public const COL_VALIDATION_ERRORS = 'validation_errors';

    public const COL_UPLOAD_STATUS = 'upload_status';

    public const COL_UPLOAD_DETAILS = 'upload_details';

    /** Число блоков «совместимость» в одной строке файла. */
    public const VEHICLE_SLOTS = 5;

    /**
     * Генерация пустого шаблона для продавца.
     */
    public function buildTemplate(string $targetPath): void
    {
        $dir = dirname($targetPath);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать директорию: '.$dir);
        }

        $spreadsheet = new Spreadsheet;
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle(self::SHEET_DATA);

        $headers = $this->templateHeaders();
        $col = 1;
        foreach ($headers as $h) {
            $dataSheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $h);
            $col++;
        }
        $dataSheet->freezePane('A2');

        $help = $spreadsheet->createSheet();
        $help->setTitle(self::SHEET_HELP);
        $help->setCellValue('A1', 'Как заполнять');
        $help->setCellValue('A2', 'Лист «'.self::SHEET_DATA.'»: одна строка = одна позиция на площадке.');
        $help->setCellValue('A3', 'Обязательно: название, хотя бы один URL/путь к фото, цена, себестоимость, количество, OEM, артикул, срок отгрузки (дней).');
        $help->setCellValue('A4', 'Фото: до 12 штук, через запятую. Допустимы HTTPS-ссылки или пути файлов в хранилище public (как после загрузки в кабинете), например seller-listings/photo.jpg');
        $help->setCellValue('A5', 'Совместимость: заполните vehicle_N_make и vehicle_N_model; укажите vehicle_N_years (формат как в подсказке в админке) и/или vehicle_N_row_ids (id строк справочника «Автомобили» через запятую). Хотя бы один блок с маркой/моделью.');
        $help->setCellValue('A6', SellerListingVehicleCompatibilities::freeformCompatibilityYearsFieldHint());
        $help->setCellValue('A7', 'После проверки/загрузки в файле заполнятся колонки validation_*, upload_* — не удаляйте их заголовки при повторной загрузке.');

        $spreadsheet->setActiveSheetIndex(0);
        (new Xlsx($spreadsheet))->save($targetPath);
    }

    /**
     * @return array{
     *   rows_total: int,
     *   rows_data: int,
     *   valid: int,
     *   invalid: int,
     *   uploaded: int,
     *   output_path: string
     * }
     */
    public function processFile(string $inputPath, string $outputPath, int $sellerId, bool $apply): array
    {
        if (! is_file($inputPath)) {
            throw new \InvalidArgumentException('Файл не найден: '.$inputPath);
        }

        $spreadsheet = IOFactory::load($inputPath);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_DATA)
            ?? $spreadsheet->getActiveSheet();

        $headerMap = $this->readHeaderMap($sheet, 1);
        if ($headerMap === []) {
            throw new \RuntimeException('Не найдена строка заголовков (лист «'.self::SHEET_DATA.'» или активный лист).');
        }

        $this->ensureResultColumns($sheet, $headerMap);

        $highestRow = (int) $sheet->getHighestDataRow();
        $stats = [
            'rows_total' => max(0, $highestRow - 1),
            'rows_data' => 0,
            'valid' => 0,
            'invalid' => 0,
            'uploaded' => 0,
            'output_path' => $outputPath,
        ];

        $warehouseOk = Warehouse::query()
            ->where('seller_id', $sellerId)
            ->where('is_active', true)
            ->exists();

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = $this->readDataRow($sheet, $headerMap, $row);
            if ($this->isRowEmpty($rowData)) {
                $this->writeResultCells($sheet, $headerMap, $row, '', '', '', '');

                continue;
            }

            $stats['rows_data']++;

            if (! $warehouseOk) {
                $this->writeResultCells(
                    $sheet,
                    $headerMap,
                    $row,
                    'error',
                    'Нет активного склада продавца. Обратитесь к администрации площадки.',
                    'skipped',
                    ''
                );
                $stats['invalid']++;

                continue;
            }

            try {
                $payload = $this->buildListingPayload($rowData);
                $this->validatePayload($payload);
                SellerListingVehicleCompatibilities::assertSellerRowsHavePickOrYears($payload['vehicle_compatibilities']);
                $stats['valid']++;

                if ($apply) {
                    $listing = app(SellerSubmittedProductService::class)->createListing($sellerId, $payload);
                    $stats['uploaded']++;
                    $this->writeResultCells(
                        $sheet,
                        $headerMap,
                        $row,
                        'ok',
                        '',
                        'uploaded',
                        'seller_product_id='.$listing->id.'; product_id='.$listing->product_id
                    );
                } else {
                    $this->writeResultCells(
                        $sheet,
                        $headerMap,
                        $row,
                        'ok',
                        '',
                        'not_uploaded',
                        'Проверка пройдена; загрузка не выполнялась (режим только валидации).'
                    );
                }
            } catch (ValidationException $e) {
                $stats['invalid']++;
                $msg = $this->flattenValidationMessages($e);
                $this->writeResultCells($sheet, $headerMap, $row, 'error', $msg, 'skipped', '');
            } catch (\Throwable $e) {
                $stats['invalid']++;
                $this->writeResultCells(
                    $sheet,
                    $headerMap,
                    $row,
                    'error',
                    $e->getMessage(),
                    'skipped',
                    ''
                );
            }
        }

        (new Xlsx($spreadsheet))->save($outputPath);

        return $stats;
    }

    public function canStaffImport(SellerStaff $staff): bool
    {
        return $staff->can(StaffPermission::CATALOG_MANAGE);
    }

    /**
     * @return list<string>
     */
    protected function templateHeaders(): array
    {
        $headers = [
            'listing_name',
            'image_urls',
            'price',
            'cost_price',
            'quantity',
            'oem_code',
            'article',
            'shipping_days',
        ];
        for ($i = 1; $i <= self::VEHICLE_SLOTS; $i++) {
            $headers[] = "vehicle_{$i}_make";
            $headers[] = "vehicle_{$i}_model";
            $headers[] = "vehicle_{$i}_years";
            $headers[] = "vehicle_{$i}_row_ids";
        }
        $headers[] = self::COL_VALIDATION_STATUS;
        $headers[] = self::COL_VALIDATION_ERRORS;
        $headers[] = self::COL_UPLOAD_STATUS;
        $headers[] = self::COL_UPLOAD_DETAILS;

        return $headers;
    }

    /**
     * @return array<string, string> header => column letter
     */
    protected function readHeaderMap(Worksheet $sheet, int $headerRow): array
    {
        $highestColumn = $sheet->getHighestColumn($headerRow);
        $maxCol = Coordinate::columnIndexFromString($highestColumn);
        $map = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $raw = $sheet->getCell($letter.$headerRow)->getValue();
            $key = is_string($raw) ? trim($raw) : trim((string) $raw);
            if ($key !== '') {
                $map[$key] = $letter;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $headerMap
     */
    protected function ensureResultColumns(Worksheet $sheet, array &$headerMap): void
    {
        $required = [
            self::COL_VALIDATION_STATUS,
            self::COL_VALIDATION_ERRORS,
            self::COL_UPLOAD_STATUS,
            self::COL_UPLOAD_DETAILS,
        ];
        $highestColumn = $sheet->getHighestColumn(1);
        $nextIndex = Coordinate::columnIndexFromString($highestColumn) + 1;
        foreach ($required as $colName) {
            if (! isset($headerMap[$colName])) {
                $letter = Coordinate::stringFromColumnIndex($nextIndex);
                $sheet->setCellValue($letter.'1', $colName);
                $headerMap[$colName] = $letter;
                $nextIndex++;
            }
        }
    }

    /**
     * @param  array<string, string>  $headerMap
     * @return array<string, string>
     */
    protected function readDataRow(Worksheet $sheet, array $headerMap, int $row): array
    {
        $out = [];
        foreach ($headerMap as $name => $letter) {
            if (str_starts_with($name, 'validation_') || str_starts_with($name, 'upload_')) {
                continue;
            }
            $v = $sheet->getCell($letter.$row)->getValue();
            $out[$name] = $v === null ? '' : trim((string) $v);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $rowData
     */
    protected function isRowEmpty(array $rowData): bool
    {
        foreach ($rowData as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $rowData
     * @return array{vehicle_compatibilities: list<array<string, mixed>>, listing_name: string, listing_images: list<string>, price: mixed, cost_price: mixed, quantity: mixed, oem_code: string, article: string, shipping_days: mixed}
     */
    protected function buildListingPayload(array $rowData): array
    {
        $imagesRaw = $rowData['image_urls'] ?? '';
        $paths = $this->parseImageList($imagesRaw);

        $compat = [];
        for ($i = 1; $i <= self::VEHICLE_SLOTS; $i++) {
            $make = trim((string) ($rowData["vehicle_{$i}_make"] ?? ''));
            $model = trim((string) ($rowData["vehicle_{$i}_model"] ?? ''));
            $years = trim((string) ($rowData["vehicle_{$i}_years"] ?? ''));
            $idsRaw = trim((string) ($rowData["vehicle_{$i}_row_ids"] ?? ''));
            if ($make === '' && $model === '' && $years === '' && $idsRaw === '') {
                continue;
            }
            $compat[] = [
                'vehicle_make' => $make,
                'vehicle_model' => $model,
                'compatibility_years' => $years,
                'vehicle_row_ids' => $this->parseIntList($idsRaw),
            ];
        }

        return [
            'listing_name' => trim((string) ($rowData['listing_name'] ?? '')),
            'listing_images' => $paths,
            'price' => $rowData['price'] ?? '',
            'cost_price' => $rowData['cost_price'] ?? '',
            'quantity' => $rowData['quantity'] ?? '',
            'oem_code' => trim((string) ($rowData['oem_code'] ?? '')),
            'article' => trim((string) ($rowData['article'] ?? '')),
            'shipping_days' => $rowData['shipping_days'] ?? '',
            'vehicle_compatibilities' => SellerListingVehicleCompatibilities::normalizeRepeaterRows($compat),
        ];
    }

    /**
     * @return list<string>
     */
    protected function parseImageList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t === '') {
                continue;
            }
            $this->assertSafeImageToken($t);
            $out[] = $t;
        }

        return array_slice(array_values(array_unique($out)), 0, 12);
    }

    protected function assertSafeImageToken(string $token): void
    {
        $lower = mb_strtolower($token);
        if (str_contains($token, '..') || str_contains($token, "\0")) {
            throw ValidationException::withMessages(['image_urls' => 'Недопустимый путь к файлу.']);
        }
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            throw ValidationException::withMessages(['image_urls' => 'Недопустимая ссылка на изображение.']);
        }
        if (preg_match('#^https?://#i', $token)) {
            if (filter_var($token, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['image_urls' => 'Некорректный URL изображения.']);
            }

            return;
        }
        $path = ltrim($token, '/');
        if (! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'image_urls' => 'Файл не найден в public-диске: '.$path,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    protected function parseIntList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (ctype_digit(trim($p))) {
                $out[] = (int) $p;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array{vehicle_compatibilities: mixed, listing_name: mixed, listing_images: mixed, price: mixed, cost_price: mixed, quantity: mixed, oem_code: mixed, article: mixed, shipping_days: mixed}  $payload
     */
    protected function validatePayload(array $payload): void
    {
        $messages = [
            'required' => 'Заполните поле «:attribute».',
            'array' => 'Поле «:attribute» должно быть списком.',
            'min.array' => 'Выберите хотя бы одно значение в поле «:attribute».',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
        ];
        $attributes = Lang::get('validation.attributes', [], 'ru');
        if (! is_array($attributes)) {
            $attributes = [];
        }

        Validator::make(
            $payload,
            [
                'vehicle_compatibilities' => ['required', 'array', 'min:1'],
                'vehicle_compatibilities.*.vehicle_make' => ['required', 'string', 'max:100'],
                'vehicle_compatibilities.*.vehicle_model' => ['required', 'string', 'max:100'],
                'vehicle_compatibilities.*.compatibility_years' => ['nullable', 'array'],
                'vehicle_compatibilities.*.compatibility_years.*' => ['integer', 'min:1900', 'max:2100'],
                'vehicle_compatibilities.*.vehicle_row_ids' => ['nullable', 'array'],
                'vehicle_compatibilities.*.vehicle_row_ids.*' => ['integer', 'exists:vehicles,id'],
                'listing_name' => ['required', 'string', 'max:500'],
                'listing_images' => ['required', 'array', 'min:1', 'max:12'],
                'listing_images.*' => ['required', 'string', 'max:500'],
                'price' => ['required', 'numeric', 'min:0'],
                'cost_price' => ['required', 'numeric', 'min:0'],
                'quantity' => ['required', 'integer', 'min:0'],
                'oem_code' => ['required', 'string', 'max:100'],
                'article' => ['required', 'string', 'max:100'],
                'shipping_days' => ['required', 'integer', 'min:0', 'max:999'],
            ],
            $messages,
            $attributes
        )->validate();
    }

    /**
     * @param  array<string, string>  $headerMap
     */
    protected function writeResultCells(
        Worksheet $sheet,
        array $headerMap,
        int $row,
        string $validationStatus,
        string $validationErrors,
        string $uploadStatus,
        string $uploadDetails,
    ): void {
        if (isset($headerMap[self::COL_VALIDATION_STATUS])) {
            $sheet->setCellValue($headerMap[self::COL_VALIDATION_STATUS].$row, $validationStatus);
        }
        if (isset($headerMap[self::COL_VALIDATION_ERRORS])) {
            $sheet->setCellValue($headerMap[self::COL_VALIDATION_ERRORS].$row, $validationErrors);
        }
        if (isset($headerMap[self::COL_UPLOAD_STATUS])) {
            $sheet->setCellValue($headerMap[self::COL_UPLOAD_STATUS].$row, $uploadStatus);
        }
        if (isset($headerMap[self::COL_UPLOAD_DETAILS])) {
            $sheet->setCellValue($headerMap[self::COL_UPLOAD_DETAILS].$row, $uploadDetails);
        }
    }

    protected function flattenValidationMessages(ValidationException $e): string
    {
        $parts = [];
        foreach ($e->errors() as $msgs) {
            foreach ($msgs as $m) {
                $parts[] = $m;
            }
        }

        return implode('; ', array_unique($parts));
    }
}
