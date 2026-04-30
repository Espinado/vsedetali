<?php

namespace App\Console\Commands;

use App\Services\SellerBulkListingXlsxService;
use Illuminate\Console\Command;

class SellerListingsImportXlsxCommand extends Command
{
    protected $signature = 'seller:listings-import-xlsx
        {path : Путь к заполненному .xlsx}
        {--seller-id= : ID продавца (sellers.id)}
        {--apply : Создать позиции в БД; без флага — только валидация и разметка файла}
        {--output= : Куда сохранить результат (по умолчанию — рядом с входным файлом, суффикс _result)}';

    protected $description = 'Проверяет Excel с позициями продавца и записывает validation_*/upload_* в файл; с --apply создаёт позиции';

    public function handle(SellerBulkListingXlsxService $service): int
    {
        $in = $this->resolvePath((string) $this->argument('path'));
        $sellerId = (int) $this->option('seller-id');
        if ($sellerId <= 0) {
            $this->error('Укажите --seller-id=');

            return self::FAILURE;
        }

        $outOpt = trim((string) $this->option('output'));
        if ($outOpt !== '') {
            $out = $this->resolvePath($outOpt);
        } else {
            $out = preg_replace('/\.xlsx$/i', '', $in).'_result.xlsx';
        }

        $apply = (bool) $this->option('apply');

        try {
            $stats = $service->processFile($in, $out, $sellerId, $apply);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Строк данных (непустых)', $stats['rows_data']],
                ['Валидных', $stats['valid']],
                ['С ошибками', $stats['invalid']],
                ['Создано позиций (--apply)', $stats['uploaded']],
                ['Файл с результатом', $stats['output_path']],
            ]
        );

        return self::SUCCESS;
    }

    protected function resolvePath(string $rawPath): string
    {
        $clean = trim($rawPath);
        if (str_starts_with($clean, '/') || (strlen($clean) > 2 && ctype_alpha($clean[0]) && $clean[1] === ':')) {
            return $clean;
        }

        return base_path(trim($clean, '/\\'));
    }
}
