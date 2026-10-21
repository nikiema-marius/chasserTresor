<?php

namespace App\Livewire\Concerns;

use App\Models\Participant;
use Illuminate\Support\Facades\Auth;

/**
 * Connexion du participant puis redirection : vers le QR scanné avant
 * l'inscription (appareil photo natif) s'il y en a un, sinon vers le tableau de bord.
 */
trait AuthenticatesParticipant
{
    protected function loginAndRedirect(Participant $participant): void
    {
        Auth::guard('participant')->login($participant, remember: true);
        session()->regenerate();

        $pendingToken = session()->pull('pending_scan_token');

        $this->redirect($pendingToken ? route('scan.link', $pendingToken) : route('dashboard'));
    }
}
