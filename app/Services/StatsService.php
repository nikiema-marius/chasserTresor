<?php

namespace App\Services;

use App\Enums\ScanResult;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\Scan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques d'une campagne (mises en cache 30 secondes).
 */
class StatsService
{
    public function forCampaign(Campaign $campaign): array
    {
        return Cache::remember('stats:campaign:'.$campaign->id, 30, fn () => $this->compute($campaign));
    }

    /** @return array<string, int> date (Y-m-d) => inscriptions */
    private function dailyRegistrations(Campaign $campaign): array
    {
        $counts = Participant::query()
            ->where('campaign_id', $campaign->id)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $days[$day] = (int) ($counts[$day] ?? 0);
        }

        return $days;
    }

    private function compute(Campaign $campaign): array
    {
        $participants = Participant::query()->where('campaign_id', $campaign->id);

        $counts = (clone $participants)->selectRaw('
            COUNT(*) AS registered,
            SUM(letters_count > 0) AS active,
            SUM(completed_at IS NOT NULL) AS completed,
            SUM(solved_at IS NOT NULL) AS solved,
            SUM(suspicion_score >= ?) AS suspects,
            SUM(consent_marketing = 1) AS marketing
        ', [config('game.suspicion.threshold')])->first();

        $registered = (int) $counts->registered;

        $byResult = Scan::query()
            ->where('campaign_id', $campaign->id)
            ->select('result', DB::raw('COUNT(*) AS total'))
            ->groupBy('result')
            ->pluck('total', 'result')
            ->map(fn ($v) => (int) $v);

        $scansTotal = (int) $byResult->sum();
        $scansValid = (int) ($byResult[ScanResult::Valid->value] ?? 0);

        $rate = fn (int $part, int $whole) => $whole > 0 ? round($part * 100 / $whole, 1) : 0.0;

        return [
            'registered' => $registered,
            'active' => (int) $counts->active,
            'completed' => (int) $counts->completed,
            'solved' => (int) $counts->solved,
            'suspects' => (int) $counts->suspects,
            'marketing' => (int) $counts->marketing,
            'scans_total' => $scansTotal,
            'scans_valid' => $scansValid,
            'conversion' => [
                'active' => $rate((int) $counts->active, $registered),
                'completed' => $rate((int) $counts->completed, $registered),
                'solved' => $rate((int) $counts->solved, $registered),
                'solved_of_completed' => $rate((int) $counts->solved, (int) $counts->completed),
                'valid_scans' => $rate($scansValid, $scansTotal),
            ],
            'by_result' => collect(ScanResult::cases())
                ->mapWithKeys(fn (ScanResult $r) => [$r->value => (int) ($byResult[$r->value] ?? 0)])
                ->filter()
                ->all(),
            'per_position' => $campaign->letters()
                ->withCount('participantLetters')
                ->get()
                ->map(fn ($l) => ['position' => $l->position, 'letter' => $l->letter, 'count' => $l->participant_letters_count])
                ->all(),
            'qr' => $campaign->qrCodes()
                ->with('campaignLetter')
                ->orderByDesc('valid_scans_count')
                ->get(['id', 'label', 'status', 'valid_scans_count', 'campaign_letter_id'])
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'label' => $q->label,
                    'position' => $q->campaignLetter->position,
                    'status' => $q->status->label(),
                    'count' => $q->valid_scans_count,
                ])
                ->all(),
            'hourly' => Scan::query()
                ->where('campaign_id', $campaign->id)
                ->where('result', ScanResult::Valid)
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS hour, COUNT(*) AS total")
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('total', 'hour')
                ->map(fn ($v) => (int) $v)
                ->all(),
            // Inscriptions par jour (14 derniers jours), jours sans inscription compris
            'daily' => $this->dailyRegistrations($campaign),
            'computed_at' => now(),
        ];
    }
}
