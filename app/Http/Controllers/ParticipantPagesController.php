<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Models\Campaign;
use App\Models\Participant;
use App\Support\LetterTiles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pages simples du participant (sans interactivité) : lettres, historique, profil, FAQ,
 * et changement de jeu.
 */
class ParticipantPagesController extends Controller
{
    /**
     * Passe sur un autre jeu : bascule sur la participation existante (même compte),
     * ou propose de rejoindre ce jeu.
     */
    public function play(Request $request, Campaign $campaign): RedirectResponse
    {
        $participation = $this->participant()->participationIn($campaign->id);

        if (! $participation) {
            return redirect()->route('join', $campaign);
        }

        if ($participation->isNot($this->participant())) {
            Auth::guard('participant')->login($participation, remember: true);
            $request->session()->regenerate();
        }

        return redirect()->route('dashboard');
    }

    public function letters(): View
    {
        $participant = $this->participant()->load('campaign.letters');

        return view('participant.letters', [
            'participant' => $participant,
            'tileGroups' => LetterTiles::for($participant, revealAll: $participant->hasSolved()),
            // Lettres dans l'ordre de découverte, sans révéler leur position dans le mot
            'found' => $participant->participantLetters()->with('campaignLetter:id,letter')->orderBy('collected_at')->get(),
        ]);
    }

    public function history(): View
    {
        $participant = $this->participant()->load('campaign');

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
        return view('participant.profile', ['participant' => $this->participant()->load('campaign')]);
    }

    public function faq(): View
    {
        $campaign = Auth::guard('participant')->user()?->campaign ?? Campaign::online()->first();

        return view('faq', ['campaign' => $campaign]);
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
