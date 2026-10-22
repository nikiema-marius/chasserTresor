<?php

namespace App\Livewire\Participant;

use App\Models\Participant;
use App\Support\GamesList;
use App\Support\LetterTiles;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tableau de bord : lettres collectées, progression, accès au scanner
 * et au mot mystère. Tout est relu en base à chaque affichage
 * (progression persistante entre sessions et appareils).
 */
#[Layout('components.layouts.app')]
#[Title('Ma partie')]
class Dashboard extends Component
{
    public function render()
    {
        /** @var Participant $participant */
        $participant = Auth::guard('participant')->user()->refresh()->load('campaign.letters');

        return view('livewire.participant.dashboard', [
            'participant' => $participant,
            'campaign' => $participant->campaign,
            'tileGroups' => LetterTiles::for($participant),
            'wordCount' => count($participant->campaign->wordLengths()),
            'availability' => $participant->campaign->availabilityAt(now()),
            'flash' => session('scan_outcome'),
            // Autres jeux en ligne (même compte) : continuer ou rejoindre
            'otherGames' => GamesList::for($participant)->reject(fn ($g) => $g['campaign']->id === $participant->campaign_id)->values(),
        ]);
    }
}
