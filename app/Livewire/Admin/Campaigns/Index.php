<?php

namespace App\Livewire\Admin\Campaigns;

use App\Enums\CampaignStatus;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Campagnes')]
class Index extends Component
{
    use InteractsWithAdmin;

    public function makeCurrent(int $id): void
    {
        $this->authorizeAdmin('manage');

        $campaign = Campaign::query()->findOrFail($id);
        $campaign->makeCurrent();

        $this->audit('campaign.made_current', $campaign);
        $this->toast("« {$campaign->name} » est la campagne publique.");
    }

    public function setStatus(int $id, string $status): void
    {
        $this->authorizeAdmin('manage');

        $campaign = Campaign::query()->findOrFail($id);
        $target = CampaignStatus::from($status);

        if ($target === CampaignStatus::Active && $problems = $campaign->activationProblems()) {
            $this->toast('Activation impossible : '.implode(' ', $problems), 'error');

            return;
        }

        $previous = $campaign->status;
        $campaign->forceFill(['status' => $target])->save();

        $this->audit('campaign.status_changed', $campaign, ['from' => $previous->value, 'to' => $target->value]);

        // Activer une campagne en fait le jeu proposé aux participants (un seul jeu public à la fois)
        if ($target === CampaignStatus::Active && ! $campaign->is_current) {
            $campaign->makeCurrent();
            $this->audit('campaign.made_current', $campaign, ['reason' => 'activation']);
            $this->toast("« {$campaign->name} » est active : c'est maintenant le jeu proposé aux participants.");

            return;
        }

        $this->toast("Statut : {$target->label()}.");
    }

    public function render()
    {
        return view('livewire.admin.campaigns.index', [
            'campaigns' => Campaign::query()
                ->withCount(['participants', 'qrCodes'])
                ->orderByDesc('starts_at')
                ->get(),
        ]);
    }
}
