<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Models\SellerProduct;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSellerProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'marketplaceSellerProducts';

    protected static ?string $title = 'Товары продавца на площадке';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof \App\Models\Warehouse && $ownerRecord->seller_id !== null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Кол-во'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'На модерации',
                        'active' => 'Активна',
                        'draft' => 'Черновик',
                        'paused' => 'Пауза',
                        'rejected' => 'Отклонена',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('catalog')
                    ->label('Каталог')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SellerProduct $record): string => ProductResource::getUrl('edit', ['record' => $record->product_id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SellerProduct $record): bool => $record->status === 'pending')
                    ->action(fn (SellerProduct $record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->headerActions([]);
    }
}
