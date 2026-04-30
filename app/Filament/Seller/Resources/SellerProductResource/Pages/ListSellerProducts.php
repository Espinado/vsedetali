<?php

namespace App\Filament\Seller\Resources\SellerProductResource\Pages;

use App\Authorization\StaffPermission;
use App\Filament\Seller\Resources\SellerProductResource;
use App\Models\SellerStaff;
use App\Services\SellerBulkListingXlsxService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListSellerProducts extends ListRecords
{
    protected static string $resource = SellerProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('downloadListingsTemplate')
                ->label('Скачать шаблон Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->sellerStaffCanCatalog())
                ->action(function (SellerBulkListingXlsxService $service): BinaryFileResponse {
                    $path = storage_path('app/templates/seller_listings_import_template.xlsx');
                    $service->buildTemplate($path);

                    return response()->download($path, 'seller_listings_import_template.xlsx');
                }),
            Actions\Action::make('importListingsExcel')
                ->label('Загрузить Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => $this->sellerStaffCanCatalog())
                ->form([
                    Forms\Components\FileUpload::make('spreadsheet')
                        ->label('Файл .xlsx')
                        ->disk('local')
                        ->directory('seller-bulk-imports')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required(),
                    Forms\Components\Toggle::make('apply')
                        ->label('Создать позиции в каталоге')
                        ->helperText('Если выключено — только проверка: в скачанном файле будут колонки validation_* и upload_* без создания записей.')
                        ->default(false),
                ])
                ->action(function (array $data, SellerBulkListingXlsxService $service): ?BinaryFileResponse {
                    $staff = auth('seller_staff')->user();
                    if (! $staff instanceof SellerStaff || ! $service->canStaffImport($staff)) {
                        Notification::make()->title('Нет доступа')->danger()->send();

                        return null;
                    }
                    $relative = $data['spreadsheet'] ?? null;
                    if (! is_string($relative) || $relative === '') {
                        Notification::make()->title('Файл не получен')->danger()->send();

                        return null;
                    }
                    $in = Storage::disk('local')->path($relative);
                    $out = storage_path('app/seller-bulk-imports/result-'.$staff->seller_id.'-'.uniqid('', true).'.xlsx');
                    $apply = (bool) ($data['apply'] ?? false);
                    try {
                        $stats = $service->processFile($in, $out, $staff->seller_id, $apply);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Ошибка обработки')->body($e->getMessage())->danger()->send();

                        return null;
                    }

                    $body = 'Строк с данными: '.$stats['rows_data'].'. Валидных: '.$stats['valid'].', с ошибками: '.$stats['invalid'].'.';
                    if ($apply) {
                        $body .= ' Создано позиций: '.$stats['uploaded'].'.';
                    }
                    Notification::make()->title('Обработка завершена')->body($body)->success()->send();

                    return response()->download($out, 'seller_import_result.xlsx')->deleteFileAfterSend(true);
                }),
        ];
    }

    protected function sellerStaffCanCatalog(): bool
    {
        $u = auth('seller_staff')->user();

        return $u instanceof SellerStaff && $u->can(StaffPermission::CATALOG_MANAGE);
    }
}
