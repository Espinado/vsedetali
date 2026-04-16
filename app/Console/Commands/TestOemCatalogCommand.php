<?php

namespace App\Console\Commands;

use App\Services\AutoPartsCatalogService;
use Illuminate\Console\Command;

class TestOemCatalogCommand extends Command
{
    protected $signature = 'catalog:test-oem {oem : OEM номер детали}';

    protected $description = 'Тестовый запрос RapidAPI Auto Parts Catalog по OEM (JSON в stdout).';

    public function handle(AutoPartsCatalogService $catalog): int
    {
        $oem = trim((string) $this->argument('oem'));
        if ($oem === '') {
            $this->error('Пустой OEM.');

            return self::FAILURE;
        }
        if (! $catalog->isConfigured()) {
            $this->error('Не задан RAPIDAPI_AUTO_PARTS_KEY в .env.');

            return self::FAILURE;
        }

        try {
            $payload = $catalog->lookupFullOemBundleForPersistence($oem);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
