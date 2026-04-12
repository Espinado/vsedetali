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

                return $label !== '' ? $label : 'Марка, модель, годы';
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
                    })
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make'))),
                Forms\Components\TextInput::make('compatibility_years')
                    ->label('Годы выпуска')
                    ->required()
                    ->placeholder('Например: 2015–2020 или 2019, 2020, 2021')
                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model')))
                    ->helperText(function (Get $get): string {
                        $opts = Vehicle::yearOptionsForMakeAndModel(
                            $get('vehicle_make'),
                            $get('vehicle_model')
                        );
                        $base = 'Несколько лет — через запятую, пробел или «;». Диапазон: 2015-2020 или 2015–2020 (длинное тире). Годы 1900–2100.';
                        if ($opts === []) {
                            return $base;
                        }

                        return $base.' В справочнике для этой пары: '.implode(', ', array_keys($opts)).'.';
                    }),
            ]);
    }
}
