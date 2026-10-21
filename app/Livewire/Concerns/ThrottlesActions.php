<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Limitation de fréquence des actions Livewire (la route /livewire/update
 * étant commune à tous les composants, chaque action sensible a sa propre limite).
 */
trait ThrottlesActions
{
    /**
     * Retourne false (et ajoute une erreur sur $field) si la limite est atteinte.
     */
    protected function attemptThrottled(string $key, int $maxAttempts, int $decaySeconds, string $field = 'throttle'): bool
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            $wait = $seconds >= 60 ? ceil($seconds / 60).' min' : $seconds.' s';
            $this->addError($field, "Trop de tentatives. Réessaie dans {$wait}.");

            return false;
        }

        RateLimiter::hit($key, $decaySeconds);

        return true;
    }
}
