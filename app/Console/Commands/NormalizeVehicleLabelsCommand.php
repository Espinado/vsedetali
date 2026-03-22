<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeVehicleLabelsCommand extends Command
{
    protected $signature = 'vehicles:normalize-labels
        {--dry-run : Показать план без изменений}';

    protected $description = 'Привести марку/модель авто к единому виду (Geely вместо GEELY) и слить дубликаты vehicles';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->warn('Режим dry-run.');
        }

        $updated = 0;
        foreach (Vehicle::query()->orderBy('id')->cursor() as $vehicle) {
            $newMake = VehicleLabelNormalizer::title($vehicle->make);
            $newModel = VehicleLabelNormalizer::title($vehicle->model);
            if ($vehicle->make === $newMake && $vehicle->model === $newModel) {
                continue;
            }
            if (! $dry) {
                $vehicle->update(['make' => $newMake, 'model' => $newModel]);
            }
            $updated++;
        }

        $this->info("Обновлено записей (make/model): {$updated}");

        $merged = 0;
        $groups = Vehicle::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Vehicle $v) => $v->make.'|'.$v->model.'|'.($v->generation ?? ''));

        foreach ($groups as $vehicles) {
            if ($vehicles->count() <= 1) {
                continue;
            }

            $keeper = $vehicles->first();
            $duplicates = $vehicles->skip(1);

            foreach ($duplicates as $dup) {
                if ($dry) {
                    $this->line("Слияние vehicle #{$dup->id} → #{$keeper->id} ({$keeper->make} {$keeper->model})");
                    $merged++;

                    continue;
                }

                DB::transaction(function () use ($keeper, $dup, &$merged) {
                    $pivots = DB::table('product_vehicle')->where('vehicle_id', $dup->id)->get();

                    foreach ($pivots as $row) {
                        $exists = DB::table('product_vehicle')
                            ->where('product_id', $row->product_id)
                            ->where('vehicle_id', $keeper->id)
                            ->exists();

                        if ($exists) {
                            DB::table('product_vehicle')->where('id', $row->id)->delete();
                        } else {
                            DB::table('product_vehicle')->where('id', $row->id)->update(['vehicle_id' => $keeper->id]);
                        }
                    }

                    $dup->delete();
                    $merged++;
                });
            }
        }

        $this->info("Слито дубликатов vehicles: {$merged}");

        return self::SUCCESS;
    }
}
