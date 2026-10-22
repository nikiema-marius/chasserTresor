<?php

namespace App\Providers;

use App\Contracts\SmsDriver;
use App\Http\Middleware\EnsureAdminSecurity;
use App\Models\Admin;
use App\Sms\LogSmsDriver;
use App\Sms\NullSmsDriver;
use App\Sms\OrangeSmsDriver;
use App\Sms\SmsException;
use App\Sms\VonageSmsDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Driver SMS choisi par SMS_DRIVER
        $this->app->singleton(SmsDriver::class, function ($app) {
            $driver = config('game.sms.driver');

            if ($driver === 'log' && $app->isProduction()) {
                throw new SmsException('Le driver SMS « log » est interdit en production : configurez orange ou vonage.');
            }

            return match ($driver) {
                'orange' => new OrangeSmsDriver(config('game.sms.orange')),
                'vonage' => new VonageSmsDriver(config('game.sms.vonage')),
                'null' => new NullSmsDriver,
                default => new LogSmsDriver,
            };
        });
    }

    public function boot(): void
    {
        $this->configureSecurity();
        $this->configureRateLimiting();
        $this->configureGates();
        $this->configureLivewire();
    }

    private function configureSecurity(): void
    {
        // En production, toutes les URL générées sont en HTTPS (requis pour la caméra)
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Proxies de confiance (Cloudflare, load balancer) : TRUSTED_PROXIES=*
        if ($proxies = config('game.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    private function configureRateLimiting(): void
    {
        $sessionOrIp = fn (Request $request) => $request->hasSession() && $request->session()->getId()
            ? $request->session()->getId()
            : $request->ip();

        // Ouverture d'un lien de QR (appareil photo natif)
        RateLimiter::for('scan-link', fn (Request $request) => [
            Limit::perMinute(30)->by('session:'.$sessionOrIp($request)),
            Limit::perMinute(300)->by('ip:'.$request->ip()),
        ]);

        // Toutes les requêtes Livewire (limite globale ; chaque action sensible a la sienne)
        RateLimiter::for('livewire', fn (Request $request) => [
            Limit::perMinute(180)->by('session:'.$sessionOrIp($request)),
            Limit::perMinute(1200)->by('ip:'.$request->ip()),
        ]);
    }

    private function configureGates(): void
    {
        // Les Gates s'appliquent au compte admin connecté (garde « admin »)
        Gate::define('manage', fn ($user) => $user instanceof Admin && $user->canManage());
        Gate::define('super', fn ($user) => $user instanceof Admin && $user->isSuperAdmin());
    }

    private function configureLivewire(): void
    {
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', 'throttle:livewire'])
            ->name('livewire.update'));

        // Réappliqué à chaque requête Livewire (actions comprises)
        Livewire::addPersistentMiddleware([EnsureAdminSecurity::class]);
    }
}
