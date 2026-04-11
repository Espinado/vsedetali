<?php

namespace App\Http\Middleware;

use App\Models\SellerStaff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Если продавца заблокировали при активной сессии персонала — выход и редирект на страницу входа.
 */
class RedirectIfSellerBlockedFromPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('seller_staff')->user();

        if ($user instanceof SellerStaff) {
            $seller = $user->seller;
            if ($seller === null || $seller->isBlocked()) {
                auth('seller_staff')->logout();
                $request->session()->regenerate();

                return redirect()
                    ->route('filament.seller.auth.login')
                    ->with('filament_seller_blocked_flash', true);
            }
        }

        return $next($request);
    }
}
