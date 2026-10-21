<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Services\TwoFactorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Activation de la double authentification (obligatoire pour les super-admins).
 */
#[Layout('components.layouts.admin-guest')]
#[Title('Double authentification')]
class TwoFactorSetup extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public string $secret = '';

    public string $code = '';

    public function mount(TwoFactorService $twoFactor): void
    {
        if ($this->admin()->two_factor_confirmed_at) {
            $this->redirectRoute('admin.dashboard');

            return;
        }

        $this->secret = $twoFactor->generateSecret();
    }

    public function confirm(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:10']]);

        $admin = $this->admin();

        if (! $twoFactor->verify($admin, $this->code, $this->secret)) {
            $this->addError('code', 'Code invalide. Vérifiez l\'heure de votre téléphone.');

            return;
        }

        $admin->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        session(['admin_2fa_passed' => true]);
        $this->audit('admin.2fa_enabled', $admin);
        $this->flashForRedirect('Double authentification activée.');

        $this->redirectRoute('admin.dashboard');
    }

    public function render(TwoFactorService $twoFactor)
    {
        return view('livewire.admin.two-factor-setup', [
            'qrSvg' => $this->secret ? $twoFactor->qrSvg($this->admin(), $this->secret) : '',
        ]);
    }
}
