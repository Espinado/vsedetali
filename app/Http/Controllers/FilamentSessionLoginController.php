<?php

namespace App\Http\Controllers;

use App\Models\SellerStaff;
use App\Models\Staff;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Filament-страница входа — Livewire обычно шлёт запросы на /livewire/update.
 * Если POST уходит на /login (нет JS, прокси, ошибка ассетов), без этого маршрута — 405.
 */
final class FilamentSessionLoginController extends Controller
{
    public function admin(Request $request): RedirectResponse
    {
        if (Auth::guard('staff')->check()) {
            return redirect()->intended($this->adminHomeUrl());
        }

        [$email, $password, $remember] = $this->credentials($request);

        if (! Auth::guard('staff')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'data.email' => __('auth.failed'),
            ]);
        }

        /** @var Staff $user */
        $user = Auth::guard('staff')->user();
        $panel = Filament::getPanel('admin');
        if (! $user->canAccessPanel($panel)) {
            Auth::guard('staff')->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Нет доступа к этой панели.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->adminHomeUrl());
    }

    public function seller(Request $request): RedirectResponse
    {
        if (Auth::guard('seller_staff')->check()) {
            return redirect()->intended($this->sellerHomeUrl());
        }

        [$email, $password, $remember] = $this->credentials($request);

        if (! Auth::guard('seller_staff')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'data.email' => __('auth.failed'),
            ]);
        }

        /** @var SellerStaff $user */
        $user = Auth::guard('seller_staff')->user();
        $panel = Filament::getPanel('seller');
        if (! $user->canAccessPanel($panel)) {
            Auth::guard('seller_staff')->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Доступ приостановлен или недоступен. Обратитесь в поддержку.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->sellerHomeUrl());
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function credentials(Request $request): array
    {
        $email = $request->input('data.email');
        if (! is_string($email) || trim($email) === '') {
            $email = $request->input('email');
        }
        $email = is_string($email) ? trim($email) : '';

        $password = $request->input('data.password');
        if (! is_string($password) || $password === '') {
            $p = $request->input('password');
            $password = is_string($p) ? $p : '';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'data.email' => 'Укажите корректный email.',
            ]);
        }
        if ($password === '') {
            throw ValidationException::withMessages([
                'data.password' => 'Введите пароль.',
            ]);
        }

        $remember = $request->boolean('data.remember') || $request->boolean('remember');

        return [$email, $password, $remember];
    }

    private function adminHomeUrl(): string
    {
        return Filament::getPanel('admin')->getUrl();
    }

    private function sellerHomeUrl(): string
    {
        return Filament::getPanel('seller')->getUrl();
    }
}
