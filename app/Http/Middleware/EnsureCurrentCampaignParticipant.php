<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Models\Participant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le compte du participant est conservé d'un jeu à l'autre ; seule la participation
 * (lettres, essais) est propre à chaque campagne.
 *
 * Si le participant connecté appartient à une ancienne campagne :
 *  - il participe déjà au jeu en cours (même numéro) → bascule sur cette participation ;
 *  - sinon → page « Rejoindre le jeu » (un clic + acceptation du règlement).
 * Un QR scanné entre-temps est conservé puis validé.
 */
class EnsureCurrentCampaignParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        $participant = Auth::guard('participant')->user();
        $current = $participant ? Campaign::current() : null;

        if (! $participant || ! $current || (int) $participant->campaign_id === (int) $current->id) {
            return $next($request);
        }

        $participation = Participant::query()
            ->where('campaign_id', $current->id)
            ->where('phone_e164', $participant->phone_e164)
            ->whereNull('anonymized_at')
            ->first();

        if ($participation) {
            Auth::guard('participant')->login($participation, remember: true);
            $request->session()->regenerate();

            return $request->isMethod('GET') ? redirect()->to($request->fullUrl()) : $next($request);
        }

        if ($token = $request->route('token')) {
            $request->session()->put('pending_scan_token', $token);
        }

        return redirect()->route('join');
    }
}
