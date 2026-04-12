<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiagnosePanelsCommand extends Command
{
    protected $signature = 'app:diagnose-panels';

    protected $description = 'Показать, как приложение видит домены витрины и панелей (после правок .env и config:clear)';

    public function handle(): int
    {
        $this->line('APP_URL raw: '.json_encode(config('app.url')));
        $this->line('panels.admin.domain: '.json_encode(config('panels.admin.domain')));
        $this->line('panels.seller.domain: '.json_encode(config('panels.seller.domain')));
        $this->line('panels.storefront_domain: '.json_encode(config('panels.storefront_domain')));

        $appUrl = trim((string) config('app.url'));
        $normalized = (str_starts_with($appUrl, 'http://') || str_starts_with($appUrl, 'https://'))
            ? $appUrl
            : 'https://'.$appUrl;
        $parsed = parse_url($normalized, PHP_URL_HOST);
        $this->line('Витрина (хост из APP_URL): '.json_encode(is_string($parsed) ? $parsed : null));

        $admin = (string) config('panels.admin.domain');
        $seller = (string) config('panels.seller.domain');
        $panelsDedicated = $admin !== '' || $seller !== '';
        $this->line('panelsUseDedicatedHosts (ожидается true на проде): '.($panelsDedicated ? 'true' : 'false'));

        $routesPath = base_path('routes/web.php');
        $routesOk = File::exists($routesPath) && str_contains(File::get($routesPath), 'panelsUseDedicatedHosts');
        $this->line('routes/web.php содержит логику поддоменов: '.($routesOk ? 'да' : 'НЕТ — залейте актуальный файл с репозитория'));

        $filament = base_path('bootstrap/providers.php');
        $filamentOk = File::exists($filament) && str_contains(File::get($filament), 'Filament');
        $this->line('Filament в bootstrap/providers.php: '.($filamentOk ? 'да' : 'нет'));

        $this->newLine();
        $this->comment('Если panels.admin.domain пустой — проверьте .env рядом с artisan, синтаксис .env, затем: php artisan config:clear');
        $this->comment('После правок не запускайте config:cache, пока не убедитесь, что значения верные (или удалите bootstrap/cache/config.php).');

        return self::SUCCESS;
    }
}
