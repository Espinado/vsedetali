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
        $envPath = base_path('.env');

        $this->line('Путь к .env: '.$envPath);
        $this->line('Файл .env существует: '.(File::exists($envPath) ? 'да' : 'НЕТ'));
        if (File::exists($envPath)) {
            $raw = File::get($envPath);
            if (str_starts_with($raw, "\xEF\xBB\xBF")) {
                $this->warn('В начале .env обнаружен UTF-8 BOM — удалите BOM (пересохраните файл как UTF-8 без BOM), иначе первая переменная может не читаться.');
            }
            $this->line('--- разбор текста .env (до config) ---');
            $this->scanEnvKeys($envPath, 'ADMIN_PANEL_DOMAIN');
            $this->scanEnvKeys($envPath, 'SELLER_PANEL_DOMAIN');
        }

        $this->newLine();
        $this->line('--- config() после Dotenv ---');
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
        if (! $panelsDedicated) {
            $this->error('Домены панелей пустые: добавьте в .env две строки без пробелов вокруг = и без # в начале:');
            $this->line('ADMIN_PANEL_DOMAIN=admin.vsedetalki.ru');
            $this->line('SELLER_PANEL_DOMAIN=seller.vsedetalki.ru');
            $this->line('Затем: php artisan config:clear');
        } else {
            $this->comment('Домены панелей заданы. После правок не запускайте config:cache с ошибочным .env (или удалите bootstrap/cache/config.php).');
        }

        return self::SUCCESS;
    }

    private function scanEnvKeys(string $envPath, string $key): void
    {
        $content = File::get($envPath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $quotedKey = preg_quote($key, '/');
        $found = false;

        foreach (explode("\n", $content) as $idx => $line) {
            $line = rtrim($line, "\r");
            if (preg_match('/^\s*#\s*'.$quotedKey.'\s*=/', $line)) {
                $found = true;
                $this->line(sprintf(
                    '  .env строка %d: %s [ЗАКОММЕНТИРОВАНА — уберите # в начале строки]',
                    $idx + 1,
                    mb_substr($line, 0, 120)
                ));

                continue;
            }
            if (preg_match('/^\s*'.$quotedKey.'\s*=/', $line)) {
                $found = true;
                $this->line(sprintf(
                    '  .env строка %d: %s [активна]',
                    $idx + 1,
                    mb_substr($line, 0, 120)
                ));
            }
        }

        if (! $found) {
            $this->warn("В файле .env нет строки «{$key}=...» (ни активной, ни с # в начале).");
        }
    }
}
