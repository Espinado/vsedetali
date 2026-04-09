<?php

namespace App\Filament\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Concerns\ChecksStaffPermissions;
use App\Filament\Resources\ChatConversationResource\Pages;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ChatConversationResource extends Resource
{
    use ChecksStaffPermissions;

    public static function canViewAny(): bool
    {
        return static::allow(StaffPermission::CHAT_MANAGE);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::allow(StaffPermission::CHAT_MANAGE);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    protected static ?string $model = ChatConversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Чаты';

    protected static ?string $modelLabel = 'Чат';

    protected static ?string $pluralModelLabel = 'Чаты';

    protected static ?string $navigationGroup = 'Поддержка';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $count = ChatConversation::conversationsAwaitingStaffReplyCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('№')->sortable(),
                Tables\Columns\TextColumn::make('customer')
                    ->label('Клиент')
                    ->state(function (ChatConversation $record): string {
                        if ($record->user) {
                            return $record->user->email;
                        }

                        return 'Гость';
                    })
                    ->description(fn (ChatConversation $record): ?string => $record->user?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('user', fn (Builder $uq) => $uq->where('email', 'like', "%{$search}%"))
                                ->orWhere('guest_token', 'like', "%{$search}%");
                            if (is_numeric($search)) {
                                $q->orWhere('id', (int) $search);
                            }
                        });
                    }),
                Tables\Columns\TextColumn::make('latestMessage.body')
                    ->label('Последнее сообщение')
                    ->limit(60)
                    ->tooltip(function (ChatConversation $record): ?string {
                        $body = $record->latestMessage?->body;

                        return $body && Str::length($body) > 60 ? $body : null;
                    })
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unread_messages_count')
                    ->label('Новые')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('last_message_at')->label('Последнее сообщение')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                Filter::make('unread_from_customer')
                    ->label('Входящие')
                    ->form([
                        Checkbox::make('only_unread')
                            ->label('Только чаты с непрочитанными сообщениями от клиента'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['only_unread'] ?? false)) {
                            return $query;
                        }

                        return $query->whereHas('messages', fn (Builder $q) => $q
                            ->where('sender', ChatMessage::SENDER_CUSTOMER)
                            ->whereNull('read_at')
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['only_unread'] ?? false)) {
                            return null;
                        }

                        return 'Только с новыми от клиента';
                    }),
            ])
            ->emptyStateHeading('Пока нет диалогов')
            ->emptyStateDescription('Когда покупатель напишет в чат на витрине, диалог появится здесь. Включите фильтр «Только с новыми», чтобы видеть непрочитанные.')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Открыть чат'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'latestMessage'])
            ->withCount([
                'messages as unread_messages_count' => fn (Builder $q) => $q
                    ->where('sender', ChatMessage::SENDER_CUSTOMER)
                    ->whereNull('read_at'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatConversations::route('/'),
            'view' => Pages\ViewChatConversation::route('/{record}'),
        ];
    }
}
