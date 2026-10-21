<?php

namespace App\Livewire\Admin\Participants;

use App\Enums\ParticipantStatus;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Participant;
use App\Services\Anonymizer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Fiche participant : lettres, historique des scans, tentatives, drapeaux de suspicion.
 * Toutes les actions sont journalisées.
 */
#[Layout('components.layouts.admin')]
#[Title('Participant')]
class Show extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public int $participantId;

    public string $reason = '';

    public function mount(Participant $participant): void
    {
        $this->participantId = $participant->id;
    }

    public function setStatus(string $status): void
    {
        $this->authorizeAdmin('manage');
        $this->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $participant = $this->participant();
        $target = ParticipantStatus::from($status);
        $previous = $participant->status;

        $participant->forceFill(['status' => $target])->save();

        $this->audit('participant.status_changed', $participant, [
            'from' => $previous->value, 'to' => $target->value, 'reason' => $this->reason,
        ]);
        $this->reset('reason');
        $this->toast("Statut : {$target->label()}.");
    }

    /** Accorde un essai supplémentaire pour le mot mystère. */
    public function grantAttempt(): void
    {
        $this->authorizeAdmin('manage');

        $participant = $this->participant();
        $participant->increment('bonus_word_attempts');

        $this->audit('participant.attempt_granted', $participant, ['reason' => $this->reason]);
        $this->reset('reason');
        $this->toast('Un essai supplémentaire a été accordé.');
    }

    public function clearFlags(): void
    {
        $this->authorizeAdmin('manage');

        $participant = $this->participant();
        $this->audit('participant.flags_cleared', $participant, ['flags' => array_keys($participant->suspicion_flags ?? [])]);
        $participant->forceFill(['suspicion_flags' => null, 'suspicion_score' => 0])->save();

        $this->toast('Signalements levés.');
    }

    public function anonymize(Anonymizer $anonymizer): void
    {
        $this->authorizeAdmin('super');

        $anonymizer->anonymize($this->participant(), 'action administrateur');
        $this->toast('Participant anonymisé.');
    }

    public function render()
    {
        $participant = $this->participant()->load([
            'campaign',
            'participantLetters' => fn ($q) => $q->with(['campaignLetter', 'qrCode'])->orderBy('collected_at'),
            'mysteryAttempts' => fn ($q) => $q->orderBy('attempt_number'),
        ]);

        return view('livewire.admin.participants.show', [
            'participant' => $participant,
            'scans' => $participant->scans()->with('qrCode')->latest('id')->limit(50)->get(),
            'sameDevice' => $participant->device_id
                ? Participant::query()->where('campaign_id', $participant->campaign_id)
                    ->where('device_id', $participant->device_id)->whereKeyNot($participant->id)->get()
                : collect(),
            'canSeePhone' => $this->admin()->can('manage'),
        ]);
    }

    private function participant(): Participant
    {
        return Participant::query()->findOrFail($this->participantId);
    }
}
