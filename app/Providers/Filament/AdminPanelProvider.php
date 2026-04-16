<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\BindPanelSessionCookie;
use App\Models\ChatConversation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $adminDomain = config('panels.admin.domain');

        $panel = $panel
            ->default()
            ->id('admin')
            ->authGuard('staff')
            ->authPasswordBroker('staff')
            ->brandName(config('app.name'))
            ->login()
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->collapsibleNavigationGroups(true)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Главная')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Каталог')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Продажи')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Финансы')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Склад')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Контент')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Настройки')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Поддержка')
                    ->collapsed(true)
                    ->extraSidebarAttributes(function (): array {
                        if (ChatConversation::conversationsAwaitingStaffReplyCount() <= 0) {
                            return [];
                        }

                        return [
                            'class' => 'fi-sidebar-group--support-awaiting-reply',
                        ];
                    }),
                NavigationGroup::make()
                    ->label('Система')
                    ->collapsed(true),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ]);

        if (filled($adminDomain)) {
            $panel = $panel->domain($adminDomain)->path('');
        } else {
            $panel = $panel->path((string) config('panels.admin.path'));
        }

        return $panel
            ->middleware([
                BindPanelSessionCookie::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.hooks.sweetalert-assets')->render()
            )
            ->renderHook(
                'panels::head.start',
                fn (): string => '<meta name="csrf-token" content="'.e(csrf_token()).'">'
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('partials.pwa-head')->render()
                    .view('filament.hooks.sidebar-navigation-storage-sync')->render()
            )
            ->renderHook(
                'panels::styles.after',
                fn (): string => view('filament.hooks.admin-sidebar-groups-styles')->render()
                    .view('filament.hooks.panel-table-layout-fix')->render()
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => view('filament.hooks.sidebar-accordion-exclusive')->render()
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => view('filament.hooks.admin-chat')->render()
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => view('partials.pwa-install-banner')->render()
            );
    }
}
