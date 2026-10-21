<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace admin :
 * - un compte désactivé est déconnecté immédiatement ;
 * - si ADMIN_2FA_REQUIRED=true, un super-admin doit avoir activé la double
 *   authentification (TOTP) et l'avoir validée pendant cette session.
 */
class EnsureAdminSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->is_active) {
            return $this->logout($request, 'Compte désactivé.');
        }

        if ($admin->isSuperAdmin() && config('game.admin_2fa_required')) {
            if (! $admin->two_factor_confirmed_at) {
                return redirect()->route('admin.2fa.setup');
            }

            if (! $request->session()->get('admin_2fa_passed')) {
                return $this->logout($request, 'Double authentification requise.');
            }
        }

        return $next($request);
    }

    private function logout(Request $request, string $message): Response
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('error', $message);
    }
}
