<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Enums\ScanSource;
use App\Models\QrCode;
use App\Services\QrTokenService;
use App\Services\ScanOutcome;
use App\Services\ScanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GET /s/{token} : URL contenue dans le QR (appareil photo natif ou scanner intégré).
 * C'est le QR qui désigne le jeu : plusieurs campagnes peuvent être en ligne en même temps.
 *
 * - Visiteur : le jeton est conservé, inscription (ou connexion) au jeu du QR, puis validation.
 * - Participant d'un autre jeu : bascule sur sa participation à ce jeu, ou page « Rejoindre ».
 * - Participant de ce jeu : le scan est validé et il voit le résultat sur son tableau de bord.
 */
class ScanLinkController extends Controller
{
    public function __invoke(Request $request, string $token, QrTokenService $tokens, ScanService $scans): RedirectResponse
    {
        $qr = $tokens->isWellFormedToken($token) ? QrCode::findByToken($token) : null;

        if (! $qr) {
            return redirect()->route('home')->with('error', ScanResult::InvalidToken->message());
        }

        $participant = Auth::guard('participant')->user();
        $campaign = $qr->campaign;

        if (! $participant) {
            session(['pending_scan_token' => $token]);

            return redirect()->route('register', ['jeu' => $campaign->slug])
                ->with('status', "Inscris-toi (ou connecte-toi) pour valider ce QR code du jeu « {$campaign->name} ».");
        }

        // QR d'un autre jeu que celui de la session : on bascule sur la bonne participation
        if ((int) $participant->campaign_id !== (int) $campaign->id) {
            $participation = $participant->participationIn($campaign->id);

            if (! $participation) {
                session(['pending_scan_token' => $token]);

                return $campaign->isOnline()
                    ? redirect()->route('join', $campaign)
                    : redirect()->route('dashboard')->with('scan_outcome', (new ScanOutcome(ScanResult::CampaignClosed))->toFlash());
            }

            Auth::guard('participant')->login($participation, remember: true);
            $request->session()->regenerate();
            $participant = $participation;
        }

        $outcome = $scans->scan($participant, $token, ScanSource::DeepLink);

        return redirect()->route('dashboard')->with('scan_outcome', $outcome->toFlash());
    }
}
