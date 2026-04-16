<?php

namespace App\Filament\Seller\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Главная';

    protected static ?string $navigationGroup = 'Главная';

    protected static ?int $navigationSort = -10;
}
