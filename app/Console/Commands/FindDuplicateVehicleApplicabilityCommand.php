<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class FindDuplicateVehicleApplicabilityCommand extends Command
{
    protected $signature = 'vehicles:find-duplicate-applicability';

    protected $description = 'Показать дубликаты в справочнике авто: одинаковые марка, модель, поколение, год от и год до';

    public function handle(): int
    {
        /** @var Collection<string, Collection<int, Vehicle>> $groups */
        $groups = Vehicle::query()
            ->orderBy('id')
            ->get()
            ->groupBy(function (Vehicle $v): string {
                $gen = Vehicle::normalizedGeneration($v->generation);

                return implode('|', [
                    $v->make,
                    $v->model,
                    $gen ?? '',
                    (string) ($v->year_from ?? ''),
                    (string) ($v->year_to ?? ''),
                ]);
            });

        $duplicates = $groups->filter(fn (Collection $g): bool => $g->count() > 1);

        if ($duplicates->isEmpty()) {
            $this->info('Дубликатов точек применимости не найдено.');

            return self::SUCCESS;
        }

        $this->warn('Найдены группы-дубликаты (одинаковые марка, модель, поколение, год от, год до):');
        foreach ($duplicates as $key => $items) {
            $this->line('');
            $this->line("<fg=yellow>{$key}</>");
            foreach ($items as $v) {
                $this->line("  id={$v->id} · {$v->adminCompatibilityPickerLabel()}");
            }
        }

        $this->line('');
        $this->comment('Объедините товары на одну запись или удалите лишние строки справочника вручную.');

        return self::SUCCESS;
    }
}
