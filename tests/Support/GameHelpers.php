<?php

namespace Tests\Support;

use App\Enums\ScanSource;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\QrCode;
use App\Services\ScanOutcome;
use App\Services\ScanService;

/**
 * Raccourcis pour construire une partie de test.
 */
trait GameHelpers
{
    /**
     * Campagne « ARTISAN » courante avec un QR par position.
     *
     * @return array{0: Campaign, 1: list<QrCode>}
     */
    protected function campaignWithQrCodes(array $attributes = [], int $perLetter = 1): array
    {
        $campaign = Campaign::factory()->create($attributes);
        $qrCodes = [];

        foreach ($campaign->letters as $letter) {
            for ($i = 0; $i < $perLetter; $i++) {
                $qrCodes[] = QrCode::factory()->forLetter($letter)->create();
            }
        }

        return [$campaign->fresh(), $qrCodes];
    }

    protected function scan(Participant $participant, QrCode $qr, ScanSource $source = ScanSource::InApp): ScanOutcome
    {
        $input = $source === ScanSource::ManualCode ? $qr->formattedManualCode() : $qr->scanUrl();

        return app(ScanService::class)->scan($participant->fresh(), $input, $source);
    }

    /** Fait collecter toutes les lettres au participant. */
    protected function collectAll(Participant $participant, array $qrCodes): void
    {
        foreach ($qrCodes as $qr) {
            $this->scan($participant, $qr);
        }
    }
}
