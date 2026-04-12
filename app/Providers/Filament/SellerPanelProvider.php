<?php

namespace App\Providers\Filament;

use App\Filament\Seller\Pages\Auth\Login as SellerPanelLogin;
use App\Filament\Seller\Pages\Dashboard;
use App\Filament\Seller\Widgets\SellerOrdersSummaryWidget;
use App\Http\Middleware\BindPanelSessionCookie;
use App\Http\Middleware\RedirectIfSellerBlockedFromPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $sellerDomain = config('panels.seller.domain');

        $panel = $panel
            ->id('seller')
            ->authGuard('seller_staff')
            ->authPasswordBroker('seller_staff')
            ->brandName(config('app.name').' — продавец')
            ->login(SellerPanelLogin::class)
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Seller/Resources'), for: 'App\\Filament\\Seller\\Resources')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                SellerOrdersSummaryWidget::class,
                Widgets\AccountWidget::class,
            ]);

        if (filled($sellerDomain)) {
            $panel = $panel->domain($sellerDomain)->path('');
        } else {
            $panel = $panel->path((string) config('panels.seller.path'));
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
                RedirectIfSellerBlockedFromPanel::class,
            ])
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.hooks.sweetalert-assets')->render()
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('partials.pwa-head')->render()
            )
            ->renderHook(
                'panels::styles.after',
                fn (): string => view('filament.hooks.panel-table-layout-fix')->render()
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => view('partials.pwa-install-banner')->render()
            );
    }
}
