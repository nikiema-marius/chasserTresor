<?php

namespace App\Livewire\Participant;

use App\Enums\ParticipantStatus;
use App\Enums\ScanResult;
use App\Livewire\Concerns\AuthenticatesParticipant;
use App\Models\Campaign;
use App\Models\Participant;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Un participant déjà inscrit à un jeu précédent rejoint le jeu en cours en un clic :
 * son compte (nom, téléphone, PIN) est repris, il accepte seulement le règlement du nouveau jeu.
 * La progression (lettres, essais) repart à zéro pour chaque campagne.
 */
#[Layout('components.layouts.app')]
#[Title('Rejoindre le jeu')]
class JoinCampaign extends Component
{
    use AuthenticatesParticipant;

    public bool $consent_rules = false;

    public bool $age_confirmed = false;

    public function mount(): void
    {
        $current = Campaign::current();

        // Déjà inscrit au jeu en cours (ou aucun jeu) : rien à rejoindre
        if (! $current || (int) $this->participant()->campaign_id === (int) $current->id) {
            $this->redirectRoute('dashboard');
        }
    }

    public function join(AuditLogger $audit): void
    {
        $campaign = Campaign::current();
        $account = $this->participant();

        if (! $campaign || $campaign->availabilityAt(now()) === ScanResult::CampaignClosed) {
            $this->addError('consent_rules', 'Ce jeu est terminé.');

            return;
        }

        $this->validate(
            ['consent_rules' => ['accepted'], 'age_confirmed' => ['accepted']],
            attributes: ['consent_rules' => 'acceptation du règlement', 'age_confirmed' => "confirmation de l'âge"],
        );

        $participation = $this->findOrCreateParticipation($campaign, $account);

        $audit->log('participant.joined_campaign', $participation, ['from_participant' => $account->id], $participation);

        $this->loginAndRedirect($participation);
    }

    public function render()
    {
        $campaign = Campaign::current();

        return view('livewire.participant.join-campaign', [
            'participant' => $this->participant(),
            'campaign' => $campaign,
            'availability' => $campaign?->availabilityAt(now()),
        ]);
    }

    /** Copie l'identité du compte dans une nouvelle participation (protégée par l'index unique). */
    private function findOrCreateParticipation(Campaign $campaign, Participant $account): Participant
    {
        $existing = fn () => Participant::query()
            ->where('campaign_id', $campaign->id)
            ->where('phone_e164', $account->phone_e164)
            ->first();

        if ($found = $existing()) {
            return $found;
        }

        $participation = new Participant([
            'campaign_id' => $campaign->id,
            'first_name' => $account->first_name,
            'last_name' => $account->last_name,
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
            return $existing();
        }

        return $participation;
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
