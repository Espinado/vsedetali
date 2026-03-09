<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Изображения';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('path')
                    ->image()
                    ->directory('products')
                    ->required()
                    ->label('Файл'),
                Forms\Components\TextInput::make('alt')
                    ->maxLength(255)
                    ->label('Alt (описание)'),
                Forms\Components\TextInput::make('sort')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_main')
                    ->label('Главное фото'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->disk('public')
                    ->label('Фото'),
                Tables\Columns\TextColumn::make('alt')->limit(30),
                Tables\Columns\TextColumn::make('sort')->sortable(),
                Tables\Columns\IconColumn::make('is_main')->boolean()->label('Главное'),
            ])
            ->defaultSort('sort')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (ProductImage $record): void {
                        if ($record->is_main) {
                            $record->product->images()->where('id', '!=', $record->id)->update(['is_main' => false]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (ProductImage $record): void {
                        if ($record->is_main) {
                            $record->product->images()->where('id', '!=', $record->id)->update(['is_main' => false]);
                        }
                    }),
                Tables\Actions\Action::make('setMain')
                    ->label('Главное')
                    ->icon('heroicon-o-star')
                    ->action(function (ProductImage $record): void {
                        $record->product->images()->update(['is_main' => false]);
                        $record->update(['is_main' => true]);
                    })
                    ->visible(fn (ProductImage $record): bool => ! $record->is_main),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->alt ?: 'Изображение';
    }
}
