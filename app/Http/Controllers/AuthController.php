<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerBlockingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        protected CustomerBlockingService $blocking
    ) {}
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if ($this->blocking->isEmailBlocked($validated['email'])) {
            return back()->withErrors([
                'email' => 'Вход с этим email недоступен. Обратитесь в магазин.',
            ])->onlyInput('email');
        }

        if ($this->blocking->isIpBlocked($request->ip())) {
            return back()->withErrors([
                'email' => 'Вход с вашего подключения временно недоступен. Обратитесь в магазин.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt($validated, $remember)) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $authUser->forceFill(['last_login_ip' => $request->ip()])->saveQuietly();

        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($this->blocking->isEmailBlocked($validated['email'])) {
            return back()->withErrors([
                'email' => 'Регистрация с этим email недоступна. Обратитесь в магазин.',
            ])->onlyInput('email');
        }

        if ($this->blocking->isIpBlocked($request->ip())) {
            return back()->withErrors([
                'email' => 'Регистрация с вашего подключения временно недоступна. Обратитесь в магазин.',
            ])->onlyInput('email');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('account.dashboard');
    }
}
