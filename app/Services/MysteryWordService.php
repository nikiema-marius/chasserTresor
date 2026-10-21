<?php

namespace App\Services;

use App\Enums\MysteryResult;
use App\Models\MysteryAttempt;
use App\Models\Participant;
use App\Support\WordNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Soumission du mot mystère : validation serveur, tentatives limitées,
 * horodatage (millisecondes) de la réussite.
 */
class MysteryWordService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SuspicionDetector $suspicion,
    ) {}

    /**
     * @return array{0: MysteryResult, 1: int} résultat et nombre d'essais restants
     */
    public function submit(Participant $participant, string $input): array
    {
        $key = 'mystery:'.$participant->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return [MysteryResult::RateLimited, $participant->remainingAttempts()];
        }

        $normalized = WordNormalizer::normalize($input);

        if ($normalized === '') {
            return [MysteryResult::Empty, $participant->remainingAttempts()];
        }

        RateLimiter::hit($key, 10);

        try {
            [$result, $participant] = DB::transaction(function () use ($participant, $normalized) {
                $participant = Participant::query()->with('campaign')->whereKey($participant->id)->lockForUpdate()->firstOrFail();
                $campaign = $participant->campaign;

                $rejection = match (true) {
                    $campaign->availabilityAt(now()) !== null => MysteryResult::CampaignClosed,
                    ! $participant->isActive() => MysteryResult::Blocked,
                    $participant->hasSolved() => MysteryResult::AlreadySolved,
                    ! $participant->hasCompleted() => MysteryResult::NotCompleted,
                    $participant->remainingAttempts() <= 0 => MysteryResult::NoAttemptsLeft,
                    default => null,
                };

                if ($rejection) {
                    return [$rejection, $participant];
                }

                $correct = $campaign->checkMysteryWord($normalized);
                $attempt = $participant->word_attempts + 1;

                // L'index unique (participant_id, attempt_number) empêche de dépasser le quota
                MysteryAttempt::query()->create([
                    'participant_id' => $participant->id,
                    'campaign_id' => $campaign->id,
                    'attempt_number' => $attempt,
                    'submitted' => substr($normalized, 0, 64),
                    'is_correct' => $correct,
                    'ip' => request()->ip(),
                ]);

                $participant->forceFill([
                    'word_attempts' => $attempt,
                    'solved_at' => $correct ? now() : null,
                ])->save();

                return [$correct ? MysteryResult::Correct : MysteryResult::Incorrect, $participant];
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // Deux soumissions simultanées : la seconde est refusée par la base
            return [MysteryResult::RateLimited, $participant->refresh()->remainingAttempts()];
        }

        if ($result === MysteryResult::Correct) {
            $this->audit->log('mystery.solved', $participant, ['attempt' => $participant->word_attempts], $participant);
        }

        if ($result === MysteryResult::Incorrect && $participant->remainingAttempts() === 0) {
            $this->suspicion->afterAttemptsExhausted($participant);
        }

        return [$result, $participant->remainingAttempts()];
    }
}
