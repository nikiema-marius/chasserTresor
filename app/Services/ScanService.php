<?php

namespace App\Services;

use App\Enums\QrStatus;
use App\Enums\ScanResult;
use App\Enums\ScanSource;
use App\Enums\ValidationMode;
use App\Exceptions\ScanRejected;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\ParticipantLetter;
use App\Models\QrCode;
use App\Models\QrExclusiveClaim;
use App\Models\Scan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Validation serveur d'un scan de QR code.
 *
 * Protection contre les doubles validations, en trois couches :
 *  1. Verrou exclusif (SELECT … FOR UPDATE) sur la ligne du participant :
 *     les scans d'un même participant sont traités l'un après l'autre.
 *  2. Contraintes uniques en base (participant_letters, qr_exclusive_claims) :
 *     arbitre final, même si deux requêtes passent les vérifications en même temps.
 *  3. Rejeu automatique de la transaction en cas d'interblocage InnoDB.
 *
 * Ordre des verrous (évite les interblocages liés aux clés étrangères) :
 * participant (X) → QR (X, via l'incrément) → campagne (X, 1er scan seulement) → insertions.
 */
class ScanService
{
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly SuspicionDetector $suspicion,
    ) {}

    public function scan(Participant $participant, string $input, ScanSource $source): ScanOutcome
    {
        $input = mb_substr(trim($input), 0, 500);

        if (! $this->hitRateLimits($participant)) {
            return new ScanOutcome(ScanResult::RateLimited);
        }

        $qr = $this->resolveQr($input, $source);

        if (! $qr) {
            $this->log($participant, null, $source, ScanResult::InvalidToken, substr(hash('sha256', $input), 0, 16));
            $this->suspicion->afterInvalidScan($participant);

            return new ScanOutcome(ScanResult::InvalidToken);
        }

        // QR authentique mais d'une autre campagne (ex. participant inscrit à une ancienne campagne)
        if ((int) $qr->campaign_id !== (int) $participant->campaign_id) {
            $this->log($participant, $qr, $source, ScanResult::OtherCampaign);

            return new ScanOutcome(ScanResult::OtherCampaign);
        }

        // Vérifications rapides, sans verrou
        $campaign = $participant->campaign;
        $rejection = $campaign->availabilityAt(now()) ?? match (true) {
            ! $participant->isActive() => ScanResult::ParticipantBlocked,
            $qr->status === QrStatus::Revoked => ScanResult::Revoked,
            $qr->status === QrStatus::Disabled => ScanResult::Disabled,
            default => null,
        };

        if ($rejection) {
            $this->log($participant, $qr, $source, $rejection);

            return new ScanOutcome($rejection);
        }

        try {
            $outcome = DB::transaction(
                fn () => $this->claim($participant->id, $qr->id, $campaign, $source),
                attempts: 3,
            );
        } catch (ScanRejected $e) {
            $this->log($participant, $qr, $source, $e->result);

            return new ScanOutcome($e->result, retryAfter: $e->retryAfter);
        }

        $this->suspicion->afterValidScan($participant->refresh());

        return $outcome;
    }

    /**
     * Cœur transactionnel : toute exception annule l'ensemble.
     *
     * @throws ScanRejected
     */
    private function claim(int $participantId, int $qrId, Campaign $campaign, ScanSource $source): ScanOutcome
    {
        // 1. Verrou exclusif sur le participant
        $participant = Participant::query()->whereKey($participantId)->lockForUpdate()->firstOrFail();

        if (! $participant->isActive()) {
            throw new ScanRejected(ScanResult::ParticipantBlocked);
        }

        // Relecture du QR : son statut a pu changer depuis la vérification rapide
        $qr = QrCode::query()->with('campaignLetter')->findOrFail($qrId);

        if ($qr->status !== QrStatus::Active) {
            throw new ScanRejected($qr->status === QrStatus::Revoked ? ScanResult::Revoked : ScanResult::Disabled);
        }

        $owned = ParticipantLetter::query()->where('participant_id', $participant->id);

        if ((clone $owned)->where('qr_code_id', $qr->id)->exists()) {
            throw new ScanRejected(ScanResult::AlreadyOwnedQr);
        }

        if ((clone $owned)->where('campaign_letter_id', $qr->campaign_letter_id)->exists()) {
            throw new ScanRejected(ScanResult::AlreadyOwnedSlot);
        }

        $minDelay = $campaign->min_seconds_between_scans;
        $elapsed = $participant->last_valid_scan_at?->diffInSeconds(now(), absolute: true);

        if ($minDelay > 0 && $elapsed !== null && $elapsed < $minDelay) {
            throw new ScanRejected(ScanResult::TooFast, retryAfter: max(1, (int) ceil($minDelay - $elapsed)));
        }

        $now = now();

        // 2. Verrou exclusif sur le QR (avant toute insertion qui y ferait référence)
        QrCode::query()->whereKey($qr->id)->increment('valid_scans_count');

        // 3. Verrouillage du mot mystère au premier scan valide de la campagne
        if ($campaign->word_locked_at === null) {
            Campaign::query()->whereKey($campaign->id)->whereNull('word_locked_at')->update(['word_locked_at' => $now]);
        }

        // 4. Mode « premier arrivé » : la clé primaire désigne un seul gagnant
        if ($qr->effectiveValidationMode() === ValidationMode::FirstCome) {
            try {
                QrExclusiveClaim::query()->create([
                    'qr_code_id' => $qr->id,
                    'participant_id' => $participant->id,
                    'claimed_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new ScanRejected(ScanResult::AlreadyClaimed);
            }
        }

        $scan = $this->log($participant, $qr, $source, ScanResult::Valid);

        // 5. Attribution de la lettre : les index uniques sont le dernier rempart
        try {
            ParticipantLetter::query()->create([
                'participant_id' => $participant->id,
                'campaign_letter_id' => $qr->campaign_letter_id,
                'qr_code_id' => $qr->id,
                'scan_id' => $scan->id,
                'collected_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ScanRejected(ScanResult::AlreadyOwnedSlot);
        }

        $count = ParticipantLetter::query()->where('participant_id', $participant->id)->count();
        $completed = $count >= $campaign->word_length;
        // Sans saisie du mot : toutes les lettres trouvées = gagné (le mot est révélé)
        $solved = $completed && ! $campaign->require_word_entry;

        $participant->forceFill([
            'letters_count' => $count,
            'last_valid_scan_at' => $now,
            'completed_at' => $completed ? ($participant->completed_at ?? $now) : null,
            'solved_at' => $solved ? ($participant->solved_at ?? $now) : $participant->solved_at,
        ])->save();

        return new ScanOutcome(ScanResult::Valid, $qr->campaignLetter, $completed, $count,
            retryAfter: $completed ? null : ($minDelay ?: null), solved: $solved);
    }

    private function resolveQr(string $input, ScanSource $source): ?QrCode
    {
        if ($source === ScanSource::ManualCode) {
            return QrCode::findByManualCode($input);
        }

        $token = $this->tokens->extractToken($input);

        return $token ? QrCode::findByToken($token) : null;
    }

    private function hitRateLimits(Participant $participant): bool
    {
        $limits = [
            'scan:p:'.$participant->id => config('game.scan.max_per_minute_participant'),
            'scan:ip:'.request()->ip() => config('game.scan.max_per_minute_ip'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, (int) $max)) {
                return false;
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 60);
        }

        return true;
    }

    private function log(Participant $participant, ?QrCode $qr, ScanSource $source, ScanResult $result, ?string $fingerprint = null): Scan
    {
        return Scan::query()->create([
            'campaign_id' => $participant->campaign_id,
            'participant_id' => $participant->id,
            'qr_code_id' => $qr?->id,
            'source' => $source,
            'result' => $result,
            'token_fingerprint' => $fingerprint,
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}
