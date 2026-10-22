<?php

namespace App\Livewire\Admin\Participants;

use App\Enums\ParticipantStatus;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Campaign;
use App\Models\Participant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des participants : recherche, filtres (progression, suspects, statut), tri.
 */
#[Layout('components.layouts.admin')]
#[Title('Participants')]
class Index extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Locked]
    public int $campaignId;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $progress = '';   // '' | none | started | completed | solved

    #[Url]
    public string $status = '';

    #[Url]
    public bool $suspects = false;

    #[Url]
    public string $sort = 'recent'; // recent | letters | solved | suspicion

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'progress', 'status', 'suspects', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);
        $query = Participant::query()->where('campaign_id', $campaign->id);

        if ($term = trim(mb_substr($this->search, 0, 50))) {
            $digits = preg_replace('/\D/', '', $term);
            $query->where(function ($q) use ($term, $digits) {
                $q->where('pseudo', 'like', "%{$term}%");

                if (strlen($digits) >= 4) {
                    $q->orWhere('phone_e164', 'like', "%{$digits}%");
                }
            });
        }

        match ($this->progress) {
            'none' => $query->where('letters_count', 0),
            'started' => $query->where('letters_count', '>', 0)->whereNull('completed_at'),
            'completed' => $query->whereNotNull('completed_at')->whereNull('solved_at'),
            'solved' => $query->whereNotNull('solved_at'),
            default => null,
        };

        if ($this->status && ParticipantStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if ($this->suspects) {
            $query->where('suspicion_score', '>=', config('game.suspicion.threshold'));
        }

        match ($this->sort) {
            'letters' => $query->orderByDesc('letters_count')->orderBy('last_valid_scan_at'),
            'solved' => $query->orderByRaw('solved_at IS NULL')->orderBy('solved_at'),
            'suspicion' => $query->orderByDesc('suspicion_score'),
            default => $query->latest('id'),
        };

        return view('livewire.admin.participants.index', [
            'campaign' => $campaign,
            'participants' => $query->paginate(25),
            'threshold' => config('game.suspicion.threshold'),
        ]);
    }
}
