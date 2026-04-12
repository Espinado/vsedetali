<?php

namespace App\Filament\Forms;

use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;

final class VehicleCompatibilityRepeater
{
    public static function make(string $name = 'vehicle_compatibilities'): Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label('Совместимость с авто')
            ->addActionLabel('Добавить марку и модель')
            ->collapsible()
            ->minItems(function ($livewire): int {
                if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                    return 1;
                }
                if ($livewire instanceof \App\Filament\Resources\ProductResource\Pages\EditProduct) {
                    return 1;
                }
                // Позиции продавца: при правке тоже минимум одна строка совместимости.
                if ($livewire instanceof \App\Filament\Seller\Resources\SellerProductResource\Pages\EditSellerProduct) {
                    return 1;
                }

                return 0;
            })
            ->maxItems(50)
            ->defaultItems(1)
            ->itemLabel(function (array $state): string {
                $make = trim((string) ($state['vehicle_make'] ?? ''));
                $model = trim((string) ($state['vehicle_model'] ?? ''));
                $label = trim(implode(' ', array_filter([$make, $model])));
                if ($label === '') {
                    $label = 'Марка, модель, применимость';
                }
                $picked = $state['vehicle_row_ids'] ?? [];
                if (is_array($picked) && $picked !== []) {
                    $label .= ' · '.count($picked).' з.';
                }
                $years = $state['compatibility_years'] ?? [];
                if (is_array($years) && $years !== []) {
                    $label .= ' · '.count($years).' г.';
                }

                return $label;
            })
            ->schema([
                Forms\Components\Select::make('vehicle_make')
                    ->label('Марка')
                    ->options(fn (): array => Vehicle::query()
                        ->distinct()
                        ->orderBy('make')
                        ->pluck('make')
                        ->mapWithKeys(fn (string $m): array => [$m => $m])
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('vehicle_model', null);
                        $set('compatibility_years', []);
                        $set('vehicle_row_ids', []);
                    })
                    ->required(),
                Forms\Components\Select::make('vehicle_model')
                    ->label('Модель')
                    ->options(function (Get $get): array {
                        $make = $get('vehicle_make');
                        if (blank($make)) {
                            return [];
                        }

                        return Vehicle::query()
                            ->where('make', $make)
                            ->distinct()
                            ->orderBy('model')
                            ->pluck('model')
                            ->mapWithKeys(fn (string $m): array => [$m => $m])
                            ->all();
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('compatibility_years', []);
                        $set('vehicle_row_ids', []);
                    })
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make'))),
                Forms\Components\CheckboxList::make('vehicle_row_ids')
                    ->label('Записи из справочника')
                    ->helperText('Если для этой модели несколько строк (разные годы или поколения), отметьте нужные. Можно только так, только по годам ниже или оба способа сразу.')
                    ->options(function (Get $get): array {
                        $make = $get('vehicle_make');
                        $model = $get('vehicle_model');
                        if (blank($make) || blank($model)) {
                            return [];
                        }

                        return Vehicle::query()
                            ->where('make', $make)
                            ->where('model', $model)
                            ->orderBy('generation')
                            ->orderBy('year_from')
                            ->orderBy('year_to')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Vehicle $v): array => [$v->id => $v->adminCompatibilityPickerLabel()])
                            ->all();
                    })
                    ->searchable()
                    ->bulkToggleable()
                    ->live()
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model'))),
                Forms\Components\Select::make('compatibility_years')
                    ->label('Годы выпуска')
                    ->multiple()
                    ->searchable()
                    ->options(function (Get $get): array {
                        return Vehicle::yearSelectOptionsForCompatibilityPicker(
                            $get('vehicle_make'),
                            $get('vehicle_model')
                        );
                    })
                    ->helperText(function (Get $get): string {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return 'Сначала выберите марку и модель.';
                        }
                        $catalog = Vehicle::yearOptionsForMakeAndModel(
                            $get('vehicle_make'),
                            $get('vehicle_model')
                        );

                        return $catalog !== []
                            ? 'Необязательно, если выбраны записи из справочника выше. Иначе отметьте годы в списке — только те, что есть в справочнике для этой пары марка/модель (ввод текста недоступен).'
                            : 'Необязательно, если выбраны записи из справочника выше. В справочнике для этой пары не заданы диапазоны годов — доступен выбор любого года 1900–2100 (поиск по полю).';
                    })
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model'))),
            ]);
    }
}
