<?php

namespace App\Console\Commands;

use App\Services\VinDecoderService;
use Illuminate\Console\Command;

class CatalogVinDecodeCommand extends Command
{
    protected $signature = 'catalog:vin-decode
        {vin : VIN номер (17 символов)}
        {--json : Показать полный JSON-ответ API}
    ';

    protected $description = 'Проверяет VIN через внешний API и выводит ключевые данные авто';

    public function handle(VinDecoderService $decoder): int
    {
        if (! $decoder->isConfigured()) {
            $this->error('VIN_DECODER_BASE_URL не настроен в .env.');

            return self::FAILURE;
        }

        $vin = (string) $this->argument('vin');
        $result = $decoder->decode($vin);

        $this->line('VIN: '.$result['vin']);
        $this->line('Статус: '.($result['success'] ? 'OK' : 'Ошибка'));
        $this->line('Сообщение: '.$result['message']);

        $this->newLine();
        $this->table(
            ['Поле', 'Значение'],
            [
                ['Марка', $result['make']],
                ['Модель', $result['model']],
                ['Год', $result['model_year']],
                ['Комплектация/Trim', $result['trim']],
                ['Кузов', $result['body_class']],
                ['Двигатель', $result['engine']],
                ['Топливо', $result['fuel_type']],
                ['Трансмиссия', $result['transmission']],
                ['Привод', $result['drivetrain']],
                ['Производитель', $result['manufacturer']],
            ],
        );

        if ($this->option('json')) {
            $this->newLine();
            $this->line((string) json_encode(
                $result['raw'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ));
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}

