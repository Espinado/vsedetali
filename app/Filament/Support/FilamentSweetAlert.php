<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction as HeaderDeleteAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Model;

final class FilamentSweetAlert
{
    /**
     * Safe inside HTML double-quoted attributes (e.g. Filament x-on:click="...").
     * JSON/Js::from double quotes would terminate the attribute early.
     */
    private static function jsSingleQuotedString(string $value): string
    {
        return "'".addcslashes($value, "\\'")."'";
    }

    private static function jsRecordKey(mixed $key): string
    {
        $s = (string) $key;

        return ctype_digit($s) ? $s : self::jsSingleQuotedString($s);
    }

    public static function mixinScript(): string
    {
        return <<<'JS'
            if (typeof Swal !== 'undefined' && Swal.mixin) {
                Swal.mixin({
                    width: 'min(100vw - 1.5rem, 28rem)',
                    padding: '1.25rem',
                    backdrop: true,
                    allowOutsideClick: true,
                    buttonsStyling: false,
                    reverseButtons: true,
                    customClass: {
                        popup: 'filament-swal-popup',
                        confirmButton: 'filament-swal-btn filament-swal-btn-confirm',
                        cancelButton: 'filament-swal-btn filament-swal-btn-cancel',
                        actions: 'filament-swal-actions',
                    },
                });
            }
        JS;
    }

    private static function coreOptions(string $titleJs, string $htmlExpr, string $icon, string $confirmJs): string
    {
        return "title: {$titleJs}, html: {$htmlExpr}, icon: '{$icon}', showCancelButton: true, confirmButtonText: {$confirmJs}, cancelButtonText: 'Отмена', confirmButtonColor: '#ea580c', cancelButtonColor: '#78716c'";
    }

    public static function configureHeaderDelete(HeaderDeleteAction $action, string $title, ?string $html = null): void
    {
        $titleJs = self::jsSingleQuotedString($title);
        $htmlExpr = $html !== null ? self::jsSingleQuotedString($html) : 'null';
        $confirmJs = self::jsSingleQuotedString('Удалить');
        $inner = self::coreOptions($titleJs, $htmlExpr, 'warning', $confirmJs);

        $action
            ->modal(false)
            ->successNotification(null)
            ->failureNotification(null)
            ->alpineClickHandler("Swal.fire({ {$inner} }).then((r) => { if (r.isConfirmed) \$wire.mountAction('delete') })");
    }

    public static function configureTableDelete(TableDeleteAction $action, string $title, ?string $html = null): void
    {
        $titleJs = self::jsSingleQuotedString($title);
        $htmlExpr = $html !== null ? self::jsSingleQuotedString($html) : 'null';
        $confirmJs = self::jsSingleQuotedString('Удалить');
        $inner = self::coreOptions($titleJs, $htmlExpr, 'warning', $confirmJs);

        $action
            ->modal(false)
            ->successNotification(null)
            ->failureNotification(null)
            ->alpineClickHandler(function (Model $record) use ($inner): string {
                $key = self::jsRecordKey($record->getKey());

                return "Swal.fire({ {$inner} }).then((r) => { if (r.isConfirmed) \$wire.mountTableAction('delete', {$key}) })";
            });
    }

    public static function configureBulkDelete(DeleteBulkAction $action, string $title, string $countPrefix = 'Будет удалено записей:'): void
    {
        $titleJs = self::jsSingleQuotedString($title);
        $prefixJs = self::jsSingleQuotedString($countPrefix.' ');
        $confirmJs = self::jsSingleQuotedString('Удалить');
        $htmlExpr = "({$prefixJs} + '<strong>' + selectedRecords.length + '</strong>')";
        $inner = self::coreOptions($titleJs, $htmlExpr, 'warning', $confirmJs);

        $action
            ->modal(false)
            ->successNotification(null)
            ->failureNotification(null)
            ->alpineClickHandler("if (! selectedRecords.length) return; Swal.fire({ {$inner} }).then((r) => { if (r.isConfirmed) \$wire.mountTableBulkAction('delete') })");
    }

    public static function configureTableRowAction(
        TableAction $action,
        string $livewireActionName,
        string $title,
        ?string $html = null,
        string $icon = 'question',
        string $confirmLabel = 'Подтвердить',
    ): void {
        $titleJs = self::jsSingleQuotedString($title);
        $htmlExpr = $html !== null ? self::jsSingleQuotedString($html) : 'null';
        $confirmJs = self::jsSingleQuotedString($confirmLabel);
        $inner = self::coreOptions($titleJs, $htmlExpr, $icon, $confirmJs);

        $action
            ->modal(false)
            ->alpineClickHandler(function (Model $record) use ($inner, $livewireActionName): string {
                $key = self::jsRecordKey($record->getKey());
                $actionJs = self::jsSingleQuotedString($livewireActionName);

                return "Swal.fire({ {$inner} }).then((r) => { if (r.isConfirmed) \$wire.mountTableAction({$actionJs}, {$key}) })";
            });
    }
}
