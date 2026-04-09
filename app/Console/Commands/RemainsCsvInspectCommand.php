<?php

namespace App\Console\Commands;

use App\Services\RemainsStockCsvReader;
use Illuminate\Console\Command;

/**
 * Диагностика: первые строки файла и попытка найти шапку таблицы «Остатки».
 */
class RemainsCsvInspectCommand extends Command
{
    protected $signature = 'remains-csv:inspect
        {path : Путь к CSV (относительно корня проекта или абсолютный)}
        {--encoding=auto : auto, utf-8, cp1251}';

    protected $description = 'Показать первые строки CSV и результат поиска заголовка (Код/Артикул или Артикул/Наименование)';

    public function handle(): int
    {
        $rawPath = trim((string) $this->argument('path'), " \t\n\r\0\x0B\xC2\xA0");
        $path = str_starts_with($rawPath, '/') || (strlen($rawPath) > 2 && ctype_alpha($rawPath[0]) && $rawPath[1] === ':')
            ? $rawPath
            : base_path(trim($rawPath, '/\\'));

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $encOpt = strtolower(trim((string) $this->option('encoding')));
        $csvEncoding = match ($encOpt) {
            '', 'auto' => null,
            'utf-8', 'utf8' => 'utf-8',
            'cp1251', 'windows-1251', 'win1251' => 'cp1251',
            default => null,
        };
        if ($encOpt !== '' && $encOpt !== 'auto' && $csvEncoding === null) {
            $this->error('Опция --encoding: auto, utf-8, cp1251.');

            return self::FAILURE;
        }

        $this->info("Файл: {$path}");
        $this->newLine();

        try {
            $header = RemainsStockCsvReader::readHeaderRow($path, $csvEncoding);
            $this->info('Заголовок таблицы найден. Колонки (первые 20):');
            $this->line(implode(' | ', array_slice($header, 0, 20)));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->warn('Заголовок не распознан: '.$e->getMessage());
        }

        $this->newLine();
        $this->comment('Первые 15 физических строк (обрезка ~120 символов), как UTF-8:');
        $lines = RemainsStockCsvReader::diagnosticUtf8Lines($path, $csvEncoding, 15);
        foreach ($lines as $i => $line) {
            $num = $i + 1;
            $show = mb_strlen($line) > 120 ? mb_substr($line, 0, 120).'…' : $line;
            $this->line(sprintf('%3d | %s', $num, $show));
        }

        $this->newLine();
        $this->comment('Нужны в одной строке шапки: «Код»+«Артикул» ИЛИ «Артикул»+«Наименование» (или англ. Code/Article/Name).');

        return self::FAILURE;
    }
}
