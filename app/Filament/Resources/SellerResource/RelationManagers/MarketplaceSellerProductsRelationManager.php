<?php

namespace App\Filament\Resources\SellerResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\SellerResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\SellerProduct;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSellerProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'sellerProducts';

    protected static ?string $title = 'Товары на площадке';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return SellerResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['product', 'warehouse']))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->placeholder('—'),
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
                tap(
                    Tables\Actions\Action::make('approve')
                        ->label('Одобрить')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (SellerProduct $record): bool => $record->status === 'pending')
                        ->action(function (SellerProduct $record): void {
                            if ($record->seller?->isBlocked()) {
                                Notification::make()
                                    ->title('Продавец заблокирован')
                                    ->body('Сначала смените статус продавца на «Активен», затем одобряйте листинги.')
                                    ->danger()
                                    ->send();

                                return;
                            }
                            $record->update(['status' => 'active']);
                            $record->product?->update(['is_active' => true]);
                        }),
                    function (Tables\Actions\Action $action): void {
                        FilamentSweetAlert::configureTableRowAction(
                            $action,
                            'approve',
                            'Одобрить позицию на площадке?',
                            'Товар станет доступен на витрине.',
                            'question',
                            'Одобрить',
                        );
                    }
                ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->headerActions([]);
    }
}
