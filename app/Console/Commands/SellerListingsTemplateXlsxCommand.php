<?php

namespace App\Console\Commands;

use App\Services\SellerBulkListingXlsxService;
use Illuminate\Console\Command;

class SellerListingsTemplateXlsxCommand extends Command
{
    protected $signature = 'seller:listings-template-xlsx
        {path? : Путь к .xlsx (по умолчанию storage/app/templates/seller_listings_import_template.xlsx)}';

    protected $description = 'Создаёт Excel-шаблон массовой загрузки позиций продавца (маркетплейс)';

    public function handle(SellerBulkListingXlsxService $service): int
    {
        $path = $this->resolvePath((string) ($this->argument('path') ?? ''));
        try {
            $service->buildTemplate($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Шаблон: '.$path);

        return self::SUCCESS;
    }

    protected function resolvePath(string $rawPath): string
    {
        $clean = trim($rawPath);
        if ($clean === '') {
            return storage_path('app/templates/seller_listings_import_template.xlsx');
        }
        if (str_starts_with($clean, '/') || (strlen($clean) > 2 && ctype_alpha($clean[0]) && $clean[1] === ':')) {
            return $clean;
        }

        return base_path(trim($clean, '/\\'));
    }
}
