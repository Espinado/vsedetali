<?php

namespace App\Filament\Seller\Pages\Auth;

use App\Models\SellerStaff;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (session()->pull('filament_seller_blocked_flash', false)) {
            Notification::make()
                ->title('Доступ приостановлен')
                ->body('Администрация площадки ограничила вход в кабинет продавца. По вопросам обратитесь в поддержку.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user instanceof SellerStaff) {
            $seller = $user->seller;
            if ($seller === null || $seller->isBlocked()) {
                Filament::auth()->logout();

                throw ValidationException::withMessages([
                    'data.email' => 'Доступ приостановлен администрацией площадки. По вопросам обратитесь в поддержку.',
                ]);
            }
        }

        if (
            ($user instanceof FilamentUser)
            && (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
