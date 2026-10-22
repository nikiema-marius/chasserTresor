<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Participant;
use Illuminate\Support\Collection;

/**
 * Jeux en ligne, avec la participation du compte connecté à chacun (ou null s'il n'y joue pas).
 */
final class GamesList
{
    /** @return Collection<int, array{campaign: Campaign, participation: ?Participant}> */
    public static function for(?Participant $account): Collection
    {
        $online = Campaign::online();

        $participations = $account
            ? $account->accountParticipations()->whereIn('campaign_id', $online->pluck('id'))->get()->keyBy('campaign_id')
            : collect();

        return $online->map(fn (Campaign $campaign) => [
            'campaign' => $campaign,
            'participation' => $participations->get($campaign->id),
        ]);
    }
}
