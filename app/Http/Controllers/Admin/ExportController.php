<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\RewardWinner;
use App\Models\Scan;
use App\Services\AuditLogger;
use App\Support\CsvWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV maîtrisés (RGPD) : réservés aux super-admins, journalisés,
 * numéros de téléphone masqués sauf demande explicite (?complet=1).
 */
class ExportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function participants(Request $request, Campaign $campaign): StreamedResponse
    {
        $full = $request->boolean('complet');
        $marketingOnly = $request->boolean('marketing');

        $this->audit->log('export.participants', $campaign, ['full_phone' => $full, 'marketing_only' => $marketingOnly]);

        $query = Participant::query()
            ->where('campaign_id', $campaign->id)
            ->whereNull('anonymized_at')
            ->when($marketingOnly, fn ($q) => $q->where('consent_marketing', true));

        return CsvWriter::download(
            $this->filename($campaign, 'participants'),
            ['ID', 'Prénom', 'Nom', 'Téléphone', 'Inscrit le', 'Lettres', 'Complété le', 'Mot trouvé le',
                'Tentatives', 'Statut', 'Score suspicion', 'Consentement Orange Money'],
            function () use ($query, $full) {
                foreach ($query->lazyById(500) as $p) {
                    yield [
                        $p->id, $p->first_name, $p->last_name,
                        $full ? $p->phone_e164 : $p->masked_phone,
                        $p->created_at, $p->letters_count, $p->completed_at, $p->solved_at,
                        $p->word_attempts, $p->status->label(), $p->suspicion_score, $p->consent_marketing,
                    ];
                }
            },
        );
    }

    public function scans(Campaign $campaign): StreamedResponse
    {
        $this->audit->log('export.scans', $campaign);

        $query = Scan::query()->where('campaign_id', $campaign->id)->with('qrCode:id,label');

        return CsvWriter::download(
            $this->filename($campaign, 'scans'),
            ['ID', 'Date', 'Participant', 'QR', 'Emplacement', 'Source', 'Résultat', 'IP'],
            function () use ($query) {
                foreach ($query->lazyById(1000) as $s) {
                    yield [$s->id, $s->created_at, $s->participant_id, $s->qr_code_id, $s->qrCode?->label,
                        $s->source->value, $s->result->value, $s->ip];
                }
            },
        );
    }

    public function winners(Request $request, Campaign $campaign): StreamedResponse
    {
        $full = $request->boolean('complet');
        $this->audit->log('export.winners', $campaign, ['full_phone' => $full]);

        $query = RewardWinner::query()
            ->whereHas('draw', fn ($q) => $q->where('campaign_id', $campaign->id))
            ->with(['draw', 'participant']);

        return CsvWriter::download(
            $this->filename($campaign, 'gagnants'),
            ['Attribution', 'Mode', 'Lot', 'Statut attribution', 'Rang', 'Statut gagnant', 'Participant',
                'Prénom', 'Nom', 'Téléphone', 'Mot trouvé le', 'Lot remis le'],
            function () use ($query, $full) {
                foreach ($query->lazyById(500) as $w) {
                    yield [$w->reward_draw_id, $w->draw->mode->label(), $w->draw->prize_label, $w->draw->status->value,
                        $w->rank, $w->status->label(), $w->participant_id, $w->participant->first_name, $w->participant->last_name,
                        $full ? $w->participant->phone_e164 : $w->participant->masked_phone,
                        $w->participant->solved_at, $w->delivered_at];
                }
            },
        );
    }

    private function filename(Campaign $campaign, string $type): string
    {
        return "{$type}-{$campaign->slug}-".now()->format('Ymd-His').'.csv';
    }
}
