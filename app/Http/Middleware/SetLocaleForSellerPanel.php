<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Русская локаль для Filament кабинета продавца и запросов Livewire с этих страниц.
 */
class SetLocaleForSellerPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $sellerDomain = (string) config('panels.seller.domain');

        if ($sellerDomain !== '' && $request->getHost() === $sellerDomain) {
            app()->setLocale('ru');

            return $next($request);
        }

        if ($request->is('seller', 'seller/*')) {
            app()->setLocale('ru');

            return $next($request);
        }

        if ($request->is('livewire/*')) {
            $referer = (string) $request->headers->get('Referer', '');
            if ($referer !== '') {
                if ($sellerDomain !== '' && str_contains($referer, '://'.$sellerDomain)) {
                    app()->setLocale('ru');
                } elseif (str_contains($referer, '/seller')) {
                    app()->setLocale('ru');
                }
            }
        }

        return $next($request);
    }
}
