<?php

use App\Http\Middleware\EnsureAdminSecurity;
use App\Http\Middleware\EnsureCurrentCampaignParticipant;
use App\Http\Middleware\EnsureDeviceId;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Vrai si la requête vise l'espace d'administration. */
$isAdmin = fn (Request $request): bool => $request->is(config('game.admin_path'), config('game.admin_path').'/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix(config('game.admin_path'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($isAdmin) {
        $middleware->web(append: [
            EnsureDeviceId::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin.secure' => EnsureAdminSecurity::class,
            'participant.current' => EnsureCurrentCampaignParticipant::class,
        ]);

        // Visiteur non connecté → page de connexion adaptée à l'espace visité
        $middleware->redirectGuestsTo(fn (Request $request) => $isAdmin($request) ? route('admin.login') : route('register'));
        $middleware->redirectUsersTo(fn (Request $request) => $isAdmin($request) ? route('admin.dashboard') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ne jamais journaliser les secrets saisis
        $exceptions->dontFlash(['pin', 'pin_confirmation', 'code', 'password', 'word']);
    })->create();
