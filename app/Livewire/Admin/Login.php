<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ThrottlesActions;
use App\Models\Admin;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Connexion admin : email + mot de passe, puis code TOTP si la 2FA est exigée
 * (ADMIN_2FA_REQUIRED=true) et activée sur le compte.
 * 5 essais / 5 min par couple email + IP. Pas de « se souvenir de moi ».
 */
#[Layout('components.layouts.admin-guest')]
#[Title('Connexion administrateur')]
class Login extends Component
{
    use ThrottlesActions;

    public string $email = '';

    public string $password = '';

    public string $code = '';

    #[Locked]
    public string $step = 'credentials';

    #[Locked]
    public ?int $pendingAdminId = null;

    public function authenticate(AuditLogger $audit): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ], attributes: ['email' => 'email', 'password' => 'mot de passe']);

        $key = 'admin-login:'.Str::lower($this->email).'|'.request()->ip();

        if (! $this->attemptThrottled($key, 5, 300, 'email')) {
            return;
        }

        $admin = Admin::query()->where('email', Str::lower(trim($this->email)))->first();

        // Hash::check exécuté même si le compte n'existe pas : temps de réponse constant
        $valid = Hash::check($this->password, $admin?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $admin || ! $valid || ! $admin->is_active) {
            $audit->log('admin.login_failed', null, ['email' => Str::limit($this->email, 190, '')]);
            $this->addError('email', 'Identifiants invalides.');
            $this->reset('password');

            return;
        }

        RateLimiter::clear($key);
        $this->reset('password');

        if ($admin->two_factor_confirmed_at && config('game.admin_2fa_required')) {
            $this->pendingAdminId = $admin->id;
            $this->step = 'two_factor';

            return;
        }

        $this->completeLogin($admin, $audit, twoFactorPassed: false);
    }

    public function verifyCode(TwoFactorService $twoFactor, AuditLogger $audit): void
    {
        $admin = $this->pendingAdminId ? Admin::query()->find($this->pendingAdminId) : null;

        if (! $admin || $this->step !== 'two_factor') {
            $this->reset();

            return;
        }

        if (! $this->attemptThrottled('admin-2fa:'.$admin->id, 5, 300, 'code')) {
            return;
        }

        $this->validate(['code' => ['required', 'string', 'max:10']], attributes: ['code' => 'code']);

        if (! $twoFactor->verify($admin, $this->code)) {
            $audit->log('admin.2fa_failed', $admin, [], $admin);
            $this->addError('code', 'Code invalide.');

            return;
        }

        $this->completeLogin($admin, $audit, twoFactorPassed: true);
    }

    public function render()
    {
        return view('livewire.admin.login');
    }

    private function completeLogin(Admin $admin, AuditLogger $audit, bool $twoFactorPassed): void
    {
        Auth::guard('admin')->login($admin);
        session()->regenerate();
        session(['admin_2fa_passed' => $twoFactorPassed]);

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->save();
        $audit->log('admin.login', $admin, ['2fa' => $twoFactorPassed], $admin);

        $this->redirectIntended(route('admin.dashboard'));
    }
}
