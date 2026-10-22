<?php

namespace App\Livewire\Participant;

use App\Enums\ParticipantStatus;
use App\Livewire\Concerns\AuthenticatesParticipant;
use App\Models\Campaign;
use App\Models\Participant;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Le participant rejoint un autre jeu en ligne en un clic : son compte (nom,
 * téléphone, PIN) est repris, il accepte seulement le règlement de ce jeu.
 * La progression (lettres, essais) est propre à chaque campagne.
 */
#[Layout('components.layouts.app')]
#[Title('Rejoindre le jeu')]
class JoinCampaign extends Component
{
    use AuthenticatesParticipant;

    #[Locked]
    public int $campaignId;

    public bool $consent_rules = false;

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;

        if (! $campaign->isOnline()) {
            session()->flash('error', "Le jeu « {$campaign->name} » n'est pas en ligne.");
            $this->redirectRoute('home');

            return;
        }

        // Déjà inscrit à ce jeu : on bascule simplement dessus
        if ($participation = $this->participant()->participationIn($campaign->id)) {
            $this->loginAndRedirect($participation);
        }
    }

    public function join(AuditLogger $audit): void
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);
        $account = $this->participant();

        if (! $campaign->isOnline()) {
            $this->addError('consent_rules', 'Ce jeu n\'est plus en ligne.');

            return;
        }

        $this->validate(
            ['consent_rules' => ['accepted']],
            attributes: ['consent_rules' => 'acceptation du règlement'],
        );

        $participation = $this->findOrCreateParticipation($campaign, $account);

        $audit->log('participant.joined_campaign', $participation, ['from_participant' => $account->id], $participation);

        $this->loginAndRedirect($participation);
    }

    public function render()
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);

        return view('livewire.participant.join-campaign', [
            'participant' => $this->participant(),
            'campaign' => $campaign,
        ]);
    }

    /** Copie l'identité du compte dans une nouvelle participation (protégée par l'index unique). */
    private function findOrCreateParticipation(Campaign $campaign, Participant $account): Participant
    {
        if ($found = $account->participationIn($campaign->id)) {
            return $found;
        }

        $participation = new Participant([
            'campaign_id' => $campaign->id,
            'pseudo' => $account->pseudo,
            'phone_e164' => $account->phone_e164,
            'consent_rules_at' => now(),
            'consent_rules_version' => $campaign->regulation_version,
            'consent_marketing' => $account->consent_marketing,
            'age_confirmed' => true,
            'device_id' => $account->device_id,
            'registration_ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 250),
        ]);

        $participation->forceFill([
            'status' => ParticipantStatus::Active,
            'phone_verified_at' => $account->phone_verified_at,
            'pin_hash' => $account->pin_hash,
        ]);

        try {
            $participation->save();
        } catch (UniqueConstraintViolationException) {
            // Double clic simultané : la participation existe déjà
            return $account->participationIn($campaign->id);
        }

        return $participation;
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
