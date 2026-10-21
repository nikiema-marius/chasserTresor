<?php

namespace App\Services;

use App\Enums\ScanResult;
use App\Models\Participant;
use App\Models\Scan;

/**
 * Détection heuristique de participations suspectes.
 * Ne bloque jamais : ajoute des drapeaux et un score, revus par un administrateur.
 */
class SuspicionDetector
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function afterValidScan(Participant $participant): void
    {
        $config = config('game.suspicion');

        // Plusieurs comptes sur le même appareil
        if ($participant->device_id) {
            $others = Participant::query()
                ->where('campaign_id', $participant->campaign_id)
                ->where('device_id', $participant->device_id)
                ->whereKeyNot($participant->id)
                ->count();

            if ($others > 0) {
                $this->flag($participant, 'shared_device', ($others + 1).' comptes sur le même appareil');
            }
        }

        // Toutes les lettres en un temps anormalement court
        if ($participant->completed_at
            && $participant->created_at->diffInMinutes($participant->completed_at, absolute: true) < $config['fast_completion_minutes']) {
            $this->flag($participant, 'fast_completion', 'Toutes les lettres en '
                .(int) $participant->created_at->diffInMinutes($participant->completed_at, absolute: true).' min');
        }

        // Nombreux participants sur une même IP en peu de temps (signal faible : CGNAT mobile)
        $ip = request()->ip();

        if ($ip) {
            $sameIp = Scan::query()
                ->where('campaign_id', $participant->campaign_id)
                ->where('ip', $ip)
                ->where('result', ScanResult::Valid)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->distinct()
                ->count('participant_id');

            if ($sameIp >= $config['shared_ip_participants']) {
                $this->flag($participant, 'shared_ip', "{$sameIp} participants sur l'IP {$ip} en 5 min");
            }
        }
    }

    public function afterInvalidScan(Participant $participant): void
    {
        $invalid = Scan::query()
            ->where('participant_id', $participant->id)
            ->where('result', ScanResult::InvalidToken)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($invalid >= config('game.suspicion.invalid_burst')) {
            $this->flag($participant, 'invalid_burst', "{$invalid} QR invalides en 10 min");
        }
    }

    public function afterAttemptsExhausted(Participant $participant): void
    {
        $this->flag($participant, 'attempts_exhausted', 'Toutes les tentatives de mot épuisées');
    }

    /**
     * Ajoute (ou met à jour) un drapeau et recalcule le score.
     */
    public function flag(Participant $participant, string $code, string $detail): void
    {
        $flags = $participant->suspicion_flags ?? [];
        $isNew = ! isset($flags[$code]);

        $flags[$code] = ['detail' => $detail, 'at' => now()->toIso8601String()];

        $weights = config('game.suspicion.weights');
        $score = array_sum(array_map(fn ($c) => $weights[$c] ?? 0, array_keys($flags)));

        $participant->forceFill(['suspicion_flags' => $flags, 'suspicion_score' => $score])->save();

        if ($isNew) {
            $this->audit->log('participant.flagged', $participant, ['flag' => $code, 'detail' => $detail]);
        }
    }

    public static function isSuspect(Participant $participant): bool
    {
        return $participant->suspicion_score >= config('game.suspicion.threshold');
    }
}
