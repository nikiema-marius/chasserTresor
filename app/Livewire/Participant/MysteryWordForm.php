<?php

namespace App\Livewire\Participant;

use App\Enums\MysteryResult;
use App\Models\Participant;
use App\Services\MysteryWordService;
use App\Support\LetterTiles;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page « Le mot mystère » : saisie (toutes les lettres obtenues) puis écran
 * « Félicitations ! » une fois le mot trouvé.
 */
#[Layout('components.layouts.app')]
#[Title('Le mot mystère')]
class MysteryWordForm extends Component
{
    public string $word = '';

    #[Locked]
    public ?string $feedback = null;

    #[Locked]
    public bool $success = false;

    public function mount(): void
    {
        $participant = $this->participant()->refresh();

        if (! $participant->hasCompleted()) {
            $this->redirectRoute('dashboard');

            return;
        }

        // Campagne sans saisie du mot : toutes les lettres = gagné (rattrape les parties terminées avant l'option)
        if (! $participant->hasSolved() && ! $participant->campaign->require_word_entry) {
            $participant->forceFill(['solved_at' => $participant->completed_at])->save();
        }
    }

    public function submit(MysteryWordService $service): void
    {
        $this->validate(['word' => ['required', 'string', 'max:64']], attributes: ['word' => 'mot']);

        [$result, $remaining] = $service->submit($this->participant(), $this->word);

        $this->success = $result === MysteryResult::Correct;
        $this->feedback = $result->message($remaining);

        if ($this->success || $result === MysteryResult::Incorrect) {
            $this->word = '';
        }

        if ($this->success || $remaining === 0) {
            $this->dispatch('mystery-solved');
        }
    }

    public function render()
    {
        $participant = $this->participant()->refresh()->load('campaign.letters');

        return view('livewire.participant.mystery-word-form', [
            'participant' => $participant,
            'campaign' => $participant->campaign,
            'tileGroups' => LetterTiles::for($participant, revealAll: $participant->hasSolved()),
            'rank' => $participant->solvedRank(),
            'prizeCode' => $participant->hasSolved() ? $participant->prizeCode() : null,
        ])->title($participant->hasSolved() ? 'Mon gain' : 'Le mot mystère');
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
