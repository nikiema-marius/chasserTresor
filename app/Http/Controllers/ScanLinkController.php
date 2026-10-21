<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Enums\ScanSource;
use App\Services\QrTokenService;
use App\Services\ScanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * GET /s/{token} : URL contenue dans le QR, ouverte par l'appareil photo natif.
 *
 * - Participant connecté : le scan est validé puis il est redirigé vers son tableau de bord.
 * - Visiteur : le jeton est conservé en session, il s'inscrit (ou se reconnecte)
 *   puis le scan est validé automatiquement.
 */
class ScanLinkController extends Controller
{
    public function __invoke(string $token, QrTokenService $tokens, ScanService $scans): RedirectResponse
    {
        if (! $tokens->isWellFormedToken($token)) {
            return redirect()->route('home')->with('error', ScanResult::InvalidToken->message());
        }

        $participant = Auth::guard('participant')->user();

        if (! $participant) {
            session(['pending_scan_token' => $token]);

            return redirect()->route('register')
                ->with('status', 'Inscris-toi (ou connecte-toi) pour valider ce QR code.');
        }

        $outcome = $scans->scan($participant, $token, ScanSource::DeepLink);

        return redirect()->route('dashboard')->with('scan_outcome', $outcome->toFlash());
    }
}
