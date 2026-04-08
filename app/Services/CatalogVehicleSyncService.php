<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vehicle;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Support\Str;

/**
 * Привязка к товару записей Vehicle по списку из {@see AutoPartsCatalogService::lookupEnrichedForStock} (TecDoc / RapidAPI).
 */
class CatalogVehicleSyncService
{
    /**
     * Создаёт/обновляет Vehicle и связи product_vehicle по полю vehicles_normalized.
     *
     * @param  array<string, mixed>  $enriched
     * @return int число новых привязок (pivot)
     */
    public function attachFromEnrichment(Product $product, array $enriched): int
    {
        $list = $enriched['vehicles_normalized'] ?? [];
        if (! is_array($list) || $list === []) {
            return 0;
        }

        $oem = Str::limit(explode('/', (string) $product->sku)[0], 100, '');
        $attached = 0;

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $make = VehicleLabelNormalizer::title(trim((string) ($row['make'] ?? '')));
            $model = VehicleLabelNormalizer::title(trim((string) ($row['model'] ?? '')));
            if ($make === '' || $model === '') {
                continue;
            }

            $vehicle = $this->mergeOrCreateVehicle($row, $make, $model);

            if (! $product->vehicles()->where('vehicles.id', $vehicle->id)->exists()) {
                $product->vehicles()->attach($vehicle->id, ['oem_number' => $oem !== '' ? $oem : null]);
                $attached++;
            }
        }

        return $attached;
    }

    /**
     * @param  array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}  $row
     */
    protected function mergeOrCreateVehicle(array $row, string $make, string $model): Vehicle
    {
        $yf = $row['year_from'] ?? null;
        $yt = $row['year_to'] ?? null;
        $body = trim((string) ($row['body_type'] ?? ''));
        $engine = trim((string) ($row['engine'] ?? ''));

        $existing = Vehicle::query()
            ->where('make', $make)
            ->where('model', $model)
            ->whereNull('generation')
            ->first();

        if ($existing === null) {
            return Vehicle::query()->create([
                'make' => $make,
                'model' => $model,
                'generation' => null,
                'year_from' => $yf,
                'year_to' => $yt,
                'body_type' => $body !== '' ? $body : null,
                'engine' => $engine !== '' ? $engine : null,
            ]);
        }

        if ($yf !== null) {
            $existing->year_from = $existing->year_from !== null
                ? min((int) $yf, (int) $existing->year_from)
                : (int) $yf;
        }
        if ($yt !== null) {
            $existing->year_to = $existing->year_to !== null
                ? max((int) $yt, (int) $existing->year_to)
                : (int) $yt;
        }
        if ($body !== '' && ($existing->body_type === null || $existing->body_type === '')) {
            $existing->body_type = $body;
        }
        if ($engine !== '' && ($existing->engine === null || $existing->engine === '')) {
            $existing->engine = $engine;
        }
        $existing->save();

        return $existing;
    }
}
