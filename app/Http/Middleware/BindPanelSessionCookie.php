<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Разные имена session-cookie на поддоменах панелей при SESSION_DOMAIN=null —
 * явное разделение от витрины и между панелями.
 */
class BindPanelSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $adminDomain = (string) config('panels.admin.domain');
        $sellerDomain = (string) config('panels.seller.domain');

        if ($adminDomain !== '' && $host === $adminDomain) {
            config(['session.cookie' => config('panels.session_cookies.admin')]);
        } elseif ($sellerDomain !== '' && $host === $sellerDomain) {
            config(['session.cookie' => config('panels.session_cookies.seller')]);
        }

        return $next($request);
    }
}
