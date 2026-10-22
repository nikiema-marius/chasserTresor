<?php

namespace App\Livewire\Admin;

use App\Models\Campaign;
use App\Services\StatsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tableau de bord : statistiques de la campagne (rafraîchies toutes les 30 s).
 */
#[Layout('components.layouts.admin')]
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    #[Url(as: 'campagne')]
    public ?int $campaignId = null;

    public function render(StatsService $stats)
    {
        $campaigns = Campaign::query()->orderByDesc('starts_at')->get(['id', 'name', 'status', 'ends_at']);
        $campaign = $this->campaignId
            ? Campaign::query()->find($this->campaignId)
            : (Campaign::online()->first() ?? $campaigns->first());

        return view('livewire.admin.dashboard', [
            'campaigns' => $campaigns,
            'campaign' => $campaign,
            'stats' => $campaign ? $stats->forCampaign($campaign) : null,
        ]);
    }
}
