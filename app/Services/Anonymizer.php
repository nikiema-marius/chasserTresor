<?php

namespace App\Services;

use App\Models\MysteryAttempt;
use App\Models\OtpCode;
use App\Models\Participant;
use App\Models\Scan;
use Illuminate\Support\Facades\DB;

/**
 * RGPD : anonymisation irréversible d'un participant.
 * Les données de jeu (lettres, horodatages) sont conservées pour les statistiques,
 * mais plus rien ne permet d'identifier la personne.
 */
class Anonymizer
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function anonymize(Participant $participant, string $reason): void
    {
        if ($participant->anonymized_at) {
            return;
        }

        $phone = $participant->phone_e164;

        DB::transaction(function () use ($participant, $phone) {
            $participant->forceFill([
                'pseudo' => 'Anonyme #'.$participant->id,
                'phone_e164' => 'ANON-'.$participant->id,
                'pin_hash' => null,
                'device_id' => null,
                'registration_ip' => null,
                'user_agent' => null,
                'consent_marketing' => false,
                'suspicion_flags' => null,
                'remember_token' => null,
                'anonymized_at' => now(),
            ])->save();

            Scan::query()->where('participant_id', $participant->id)->update(['ip' => null, 'user_agent' => null]);
            MysteryAttempt::query()->where('participant_id', $participant->id)->update(['ip' => null]);
            OtpCode::query()->where('phone_e164', $phone)->delete();
        });

        $this->audit->log('participant.anonymized', $participant, ['reason' => $reason]);
    }
}
