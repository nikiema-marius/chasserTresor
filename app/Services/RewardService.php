<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Enums\RewardDrawStatus;
use App\Enums\RewardMode;
use App\Enums\WinnerStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\RewardDraw;
use App\Models\RewardWinner;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des récompenses en deux temps :
 *  1. propose() : l'admin génère une liste de gagnants (statut « proposé ») ;
 *  2. validate() : un super-admin valide explicitement la liste.
 * Rien n'est jamais attribué automatiquement.
 *
 * Tirage au sort vérifiable : chaque admissible reçoit le score
 * HMAC-SHA256(id, graine) ; les N plus petits scores gagnent. La graine et la liste
 * des admissibles étant conservées, n'importe qui peut refaire le calcul.
 */
class RewardService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Participants admissibles : mot trouvé, actifs, non anonymisés, sans lot déjà attribué.
     */
    public function eligibleQuery(Campaign $campaign, array $criteria = []): Builder
    {
        $query = Participant::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('solved_at')
            ->where('status', ParticipantStatus::Active)
            ->whereNull('anonymized_at')
            // Un seul lot par participant (propositions en cours comprises)
            ->whereDoesntHave('rewardWinners', fn (Builder $w) => $w
                ->where('status', '!=', WinnerStatus::Rejected)
                ->whereHas('draw', fn (Builder $d) => $d->where('status', '!=', RewardDrawStatus::Cancelled)));

        if ($criteria['exclude_suspects'] ?? true) {
            $query->where('suspicion_score', '<', config('game.suspicion.threshold'));
        }

        if (! empty($criteria['solved_before'])) {
            $query->where('solved_at', '<=', $criteria['solved_before']);
        }

        return $query;
    }

    public function propose(Campaign $campaign, RewardMode $mode, int $count, ?string $prize, array $criteria, Admin $admin): RewardDraw
    {
        if ($count < 1) {
            throw new DomainException('Le nombre de gagnants doit être au moins 1.');
        }

        return DB::transaction(function () use ($campaign, $mode, $count, $prize, $criteria, $admin) {
            $eligible = $this->eligibleQuery($campaign, $criteria);
            $eligibleIds = (clone $eligible)->orderBy('id')->pluck('id');
            $seed = null;

            $winners = match ($mode) {
                RewardMode::FirstN => $eligible->orderBy('solved_at')->orderBy('id')->limit($count)->pluck('id'),
                // Durée entre l'inscription et la réussite (la plus courte gagne)
                RewardMode::Ranking => $eligible->orderByRaw('TIMESTAMPDIFF(MICROSECOND, created_at, solved_at)')->orderBy('id')->limit($count)->pluck('id'),
                RewardMode::Draw => $this->drawIds($eligibleIds, $count, $seed = bin2hex(random_bytes(32))),
            };

            if ($winners->isEmpty()) {
                throw new DomainException('Aucun participant admissible.');
            }

            $draw = RewardDraw::query()->create([
                'campaign_id' => $campaign->id,
                'mode' => $mode,
                'prize_label' => $prize,
                'winners_count' => $count,
                // Liste des admissibles conservée : permet de vérifier le tirage a posteriori
                'eligibility' => $criteria + [
                    'eligible_count' => $eligibleIds->count(),
                    'eligible_ids' => $mode === RewardMode::Draw ? $eligibleIds->all() : null,
                ],
                'seed' => $seed,
                'created_by' => $admin->id,
            ]);

            foreach ($winners->values() as $index => $participantId) {
                RewardWinner::query()->create([
                    'reward_draw_id' => $draw->id,
                    'participant_id' => $participantId,
                    'rank' => $index + 1,
                    'status' => WinnerStatus::Proposed,
                ]);
            }

            $this->audit->log('reward.proposed', $draw, [
                'mode' => $mode->value,
                'count' => $winners->count(),
                'criteria' => $criteria,
            ], $admin);

            return $draw;
        });
    }

    /**
     * Ordre du tirage : reproductible à partir de la graine.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int>
     */
    public function drawIds(Collection $ids, int $count, string $seed): Collection
    {
        return $ids->sortBy(fn (int $id) => hash_hmac('sha256', (string) $id, $seed))->values()->take($count);
    }

    public function validate(RewardDraw $draw, Admin $admin): void
    {
        if (! $admin->isSuperAdmin()) {
            throw new DomainException('Seul un super-administrateur peut valider une attribution.');
        }

        DB::transaction(function () use ($draw, $admin) {
            $draw = RewardDraw::query()->whereKey($draw->id)->lockForUpdate()->firstOrFail();

            if ($draw->status !== RewardDrawStatus::Proposed) {
                throw new DomainException('Cette attribution n\'est plus modifiable.');
            }

            $draw->forceFill([
                'status' => RewardDrawStatus::Validated,
                'validated_by' => $admin->id,
                'validated_at' => now(),
            ])->save();

            $draw->winners()->where('status', WinnerStatus::Proposed)->update(['status' => WinnerStatus::Validated]);

            $this->audit->log('reward.validated', $draw, ['winners' => $draw->winners()->where('status', WinnerStatus::Validated)->count()], $admin);
        });
    }

    public function cancel(RewardDraw $draw, Admin $admin): void
    {
        if ($draw->status !== RewardDrawStatus::Proposed) {
            throw new DomainException('Seule une proposition non validée peut être annulée.');
        }

        $draw->forceFill(['status' => RewardDrawStatus::Cancelled])->save();
        $this->audit->log('reward.cancelled', $draw, [], $admin);
    }

    public function setWinnerStatus(RewardWinner $winner, WinnerStatus $status, Admin $admin, ?string $reason = null): void
    {
        $winner->forceFill([
            'status' => $status,
            'rejection_reason' => $status === WinnerStatus::Rejected ? $reason : $winner->rejection_reason,
            'delivered_at' => $status === WinnerStatus::Delivered ? now() : $winner->delivered_at,
        ])->save();

        $this->audit->log('reward.winner_'.$status->value, $winner, array_filter(['reason' => $reason]), $admin);
    }
}
