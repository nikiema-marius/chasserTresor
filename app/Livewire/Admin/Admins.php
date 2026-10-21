<?php

namespace App\Livewire\Admin;

use App\Enums\AdminRole;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Admin;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gestion des comptes administrateurs (super-admins uniquement).
 * Le mot de passe initial est généré et affiché une seule fois.
 */
#[Layout('components.layouts.admin')]
#[Title('Administrateurs')]
class Admins extends Component
{
    use InteractsWithAdmin;

    public string $name = '';

    public string $email = '';

    public string $role = 'admin';

    #[Locked]
    public ?string $generatedPassword = null;

    public function mount(): void
    {
        $this->authorizeAdmin('super');
    }

    public function create(): void
    {
        $this->authorizeAdmin('super');
        $this->email = mb_strtolower(trim($this->email));

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')],
            'role' => ['required', Rule::enum(AdminRole::class)],
        ], attributes: ['name' => 'nom', 'role' => 'rôle']);

        $password = Str::password(20);

        $admin = Admin::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $password,
            'role' => $this->role,
            'is_active' => true,
        ]);

        $this->audit('admin.created', $admin, ['role' => $this->role]);
        $this->generatedPassword = $password;
        $this->reset('name', 'email', 'role');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeAdmin('super');
        $admin = $this->other($id);

        $admin->forceFill(['is_active' => ! $admin->is_active])->save();
        $this->audit($admin->is_active ? 'admin.activated' : 'admin.deactivated', $admin);
    }

    public function changeRole(int $id, string $role): void
    {
        $this->authorizeAdmin('super');
        $admin = $this->other($id);

        $admin->forceFill(['role' => AdminRole::from($role)])->save();
        $this->audit('admin.role_changed', $admin, ['role' => $role]);
    }

    public function resetTwoFactor(int $id): void
    {
        $this->authorizeAdmin('super');
        $admin = $this->other($id);

        $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit('admin.2fa_reset', $admin);
        $this->toast("2FA réinitialisée pour {$admin->name}.");
    }

    public function dismissPassword(): void
    {
        $this->generatedPassword = null;
    }

    public function render()
    {
        return view('livewire.admin.admins', [
            'admins' => Admin::query()->orderBy('name')->get(),
            'me' => $this->admin(),
        ]);
    }

    /** Un admin ne peut pas modifier son propre compte (évite de s'enfermer dehors). */
    private function other(int $id): Admin
    {
        abort_if($id === $this->admin()->id, 403, 'Action impossible sur votre propre compte.');

        return Admin::query()->findOrFail($id);
    }
}
