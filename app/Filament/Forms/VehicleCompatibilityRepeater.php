<?php

namespace App\Filament\Forms;

use App\Models\Vehicle;
use App\Support\SellerListingVehicleCompatibilities;
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
                $cy = $state['compatibility_years'] ?? null;
                if (is_array($cy) && $cy !== []) {
                    $label .= ' · '.count($cy).' г.';
                } elseif (is_string($cy) && trim($cy) !== '') {
                    $label .= ' · годы';
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
                        $set('compatibility_years', null);
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
                        $set('compatibility_years', null);
                        $set('vehicle_row_ids', []);
                    })
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make'))),
                Forms\Components\CheckboxList::make('vehicle_row_ids')
                    ->label('Записи из справочника')
                    ->helperText('Блок целиком (и поиск по списку) только если есть строки справочника с годами. Поиск — при более чем шести таких строках. В подписи — годы (или поколение). Отметьте нужные и сузьте годы в поле ниже.')
                    ->options(function (Get $get): array {
                        $make = $get('vehicle_make');
                        $model = $get('vehicle_model');
                        if (blank($make) || blank($model)) {
                            return [];
                        }

                        return Vehicle::compatibilityPickerRowsWithDefinedYears($make, $model)
                            ->mapWithKeys(fn (Vehicle $v): array => [$v->id => $v->adminCompatibilityPickerLabel()])
                            ->all();
                    })
                    ->searchable(function (Get $get): bool {
                        $make = $get('vehicle_make');
                        $model = $get('vehicle_model');
                        if (blank($make) || blank($model)) {
                            return false;
                        }

                        return Vehicle::compatibilityPickerRowsWithDefinedYears($make, $model)->count() > 6;
                    })
                    ->bulkToggleable()
                    ->live()
                    ->hidden(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return true;
                        }

                        return Vehicle::compatibilityPickerRowsWithDefinedYears($get('vehicle_make'), $get('vehicle_model'))->isEmpty();
                    })
                    ->dehydrated(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return false;
                        }

                        return Vehicle::compatibilityPickerRowsWithDefinedYears($get('vehicle_make'), $get('vehicle_model'))->isNotEmpty();
                    })
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model'))),
                Forms\Components\Select::make('compatibility_years')
                    ->label('Годы выпуска')
                    ->multiple()
                    ->searchable()
                    ->options(fn (Get $get): array => Vehicle::yearSelectOptionsForCompatibilityPicker(
                        $get('vehicle_make'),
                        $get('vehicle_model')
                    ))
                    ->helperText('Если отмечены записи из справочника — укажите годы применимости (можно сузить относительно выбранных строк). Список лет — только из диапазонов, заданных в справочнике для этой пары.')
                    ->hidden(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return true;
                        }

                        return ! Vehicle::catalogHasYearRangesForMakeAndModel($get('vehicle_make'), $get('vehicle_model'));
                    })
                    ->dehydrated(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return false;
                        }

                        return Vehicle::catalogHasYearRangesForMakeAndModel($get('vehicle_make'), $get('vehicle_model'));
                    }),
                Forms\Components\TextInput::make('compatibility_years')
                    ->label('Годы выпуска')
                    ->placeholder('Например: 2015–2020 или 2019, 2020')
                    ->helperText(SellerListingVehicleCompatibilities::freeformCompatibilityYearsFieldHint())
                    ->maxLength(500)
                    ->hidden(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return true;
                        }

                        return Vehicle::catalogHasYearRangesForMakeAndModel($get('vehicle_make'), $get('vehicle_model'));
                    })
                    ->dehydrated(function (Get $get): bool {
                        if (blank($get('vehicle_make')) || blank($get('vehicle_model'))) {
                            return false;
                        }

                        return ! Vehicle::catalogHasYearRangesForMakeAndModel($get('vehicle_make'), $get('vehicle_model'));
                    })
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model'))),
            ]);
    }
}
