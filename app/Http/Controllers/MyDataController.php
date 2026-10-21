<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * RGPD : export des données personnelles du participant connecté (JSON).
 */
class MyDataController extends Controller
{
    public function export(): JsonResponse
    {
        /** @var Participant $p */
        $p = Auth::guard('participant')->user()->load(['campaign', 'participantLetters.campaignLetter', 'mysteryAttempts']);

        $data = [
            'exporte_le' => now()->toIso8601String(),
            'campagne' => $p->campaign->name,
            'identite' => [
                'prenom' => $p->first_name,
                'nom' => $p->last_name,
                'telephone' => $p->phone_e164,
                'telephone_verifie_le' => $p->phone_verified_at?->toIso8601String(),
            ],
            'consentements' => [
                'reglement_accepte_le' => $p->consent_rules_at?->toIso8601String(),
                'version_reglement' => $p->consent_rules_version,
                'communications_orange_money' => $p->consent_marketing,
            ],
            'partie' => [
                'inscrit_le' => $p->created_at?->toIso8601String(),
                'lettres_collectees' => $p->participantLetters->map(fn ($l) => [
                    'lettre' => $l->campaignLetter->letter,
                    'obtenue_le' => $l->collected_at->toIso8601String(),
                ])->values(),
                'propositions_de_mot' => $p->mysteryAttempts->map(fn ($a) => [
                    'proposition' => $a->submitted,
                    'correcte' => $a->is_correct,
                    'le' => $a->created_at->toIso8601String(),
                ])->values(),
                'mot_trouve_le' => $p->solved_at?->toIso8601String(),
            ],
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="mes-donnees-siao.json"',
            'Cache-Control' => 'no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
