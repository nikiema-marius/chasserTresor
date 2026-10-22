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

        // Plusieurs campagnes peuvent être actives (en ligne) en même temps
        $this->toast($target === CampaignStatus::Active
            ? "« {$campaign->name} » est en ligne : elle apparaît dans la liste des jeux des participants."
            : "« {$campaign->name} » : {$target->label()}.");
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
