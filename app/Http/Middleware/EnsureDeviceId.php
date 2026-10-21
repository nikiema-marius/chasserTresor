<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pose un identifiant d'appareil aléatoire (cookie chiffré, 1 an).
 * Sert uniquement à repérer les multi-comptes ; jamais bloquant à lui seul.
 */
class EnsureDeviceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = config('game.device_cookie');
        $current = $request->cookie($name);

        if (! is_string($current) || ! Str::isUuid($current)) {
            Cookie::queue(Cookie::make(
                $name,
                (string) Str::uuid(),
                60 * 24 * 365,
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $next($request);
    }
}
