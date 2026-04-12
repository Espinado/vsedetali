<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Если витрина случайно зарегистрирована без Route::domain (ошибка APP_URL / кэш конфига),
 * не отдаём главную витрины на хостах панелей — уводим на страницу входа Filament.
 */
class RedirectPanelSubdomainsFromStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $admin = (string) config('panels.admin.domain');
        $seller = (string) config('panels.seller.domain');

        if ($admin !== '' && $host === $admin) {
            if (\Illuminate\Support\Facades\Route::has('filament.admin.auth.login')) {
                return redirect()->route('filament.admin.auth.login');
            }

            return redirect('/login');
        }

        if ($seller !== '' && $host === $seller) {
            if (\Illuminate\Support\Facades\Route::has('filament.seller.auth.login')) {
                return redirect()->route('filament.seller.auth.login');
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
