<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laragon / nginx за HTTPS: корректный Request::secure() и сессия для /broadcasting/auth
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            \App\Http\Middleware\BindPanelSessionCookie::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocaleForSellerPanel::class,
        ]);

        $middleware->alias([
            'customer.not.blocked' => \App\Http\Middleware\EnsureCustomerNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
