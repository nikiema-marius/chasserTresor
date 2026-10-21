<?php

namespace App\Livewire\Participant;

use App\Models\Participant;
use App\Services\Anonymizer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * RGPD : consultation, export et suppression (anonymisation) de ses données.
 */
#[Layout('components.layouts.app')]
#[Title('Mes données')]
class MyData extends Component
{
    public string $confirmation = '';

    public function deleteAccount(Anonymizer $anonymizer): void
    {
        $this->validate(
            ['confirmation' => ['required', 'in:SUPPRIMER']],
            ['confirmation.in' => 'Tapez SUPPRIMER en majuscules pour confirmer.'],
        );

        // Le compte couvre tous les jeux : toutes les participations liées au numéro sont anonymisées
        $phone = Auth::guard('participant')->user()->phone_e164;

        Participant::query()->where('phone_e164', $phone)->whereNull('anonymized_at')->get()
            ->each(fn (Participant $p) => $anonymizer->anonymize($p, 'demande du participant'));

        Auth::guard('participant')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Vos données personnelles ont été supprimées.');

        $this->redirect(route('home'));
    }

    public function render()
    {
        return view('livewire.participant.my-data', [
            'participant' => Auth::guard('participant')->user()->load('campaign'),
        ]);
    }
}
