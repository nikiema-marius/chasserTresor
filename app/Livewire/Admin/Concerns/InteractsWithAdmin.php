<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\Admin;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Raccourcis communs aux composants d'administration.
 */
trait InteractsWithAdmin
{
    protected function admin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /** Vérifie un droit (Gate « manage » ou « super ») ; 403 sinon. */
    protected function authorizeAdmin(string $ability): void
    {
        abort_unless($this->admin()->can($ability), 403);
    }

    protected function audit(string $event, $subject = null, array $properties = []): void
    {
        app(AuditLogger::class)->log($event, $subject, $properties, $this->admin());
    }

    /** Notification affichée immédiatement (action sans rechargement de page). */
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('notify', message: $message, type: $type);
    }

    /** Message conservé pour la page suivante (à utiliser avant une redirection). */
    protected function flashForRedirect(string $message, string $type = 'success'): void
    {
        session()->flash($type === 'success' ? 'status' : 'error', $message);
    }
}
