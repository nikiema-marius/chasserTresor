<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Models\Campaign;
use App\Models\Participant;
use App\Support\LetterTiles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Pages simples du participant (sans interactivité) : lettres, historique, profil, FAQ.
 */
class ParticipantPagesController extends Controller
{
    public function letters(): View
    {
        $participant = $this->participant();

        return view('participant.letters', [
            'participant' => $participant,
            'tileGroups' => LetterTiles::for($participant, revealAll: $participant->hasSolved()),
            // Lettres dans l'ordre de découverte, sans révéler leur position dans le mot
            'found' => $participant->participantLetters()->with('campaignLetter:id,letter')->orderBy('collected_at')->get(),
        ]);
    }

    public function history(): View
    {
        $participant = $this->participant();

        return view('participant.history', [
            'participant' => $participant,
            'scans' => $participant->scans()
                ->with('qrCode.campaignLetter:id,letter')
                ->latest('id')
                ->limit(100)
                ->get(),
            'valid' => ScanResult::Valid,
        ]);
    }

    public function profile(): View
    {
        return view('participant.profile', ['participant' => $this->participant()]);
    }

    public function faq(): View
    {
        return view('faq', ['campaign' => Campaign::current()]);
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user()->load('campaign.letters');
    }
}
