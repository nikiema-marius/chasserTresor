<?php

namespace App\Livewire\Admin\Prizes;

use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Chapiteau Orange Money : vérification du justificatif gagnant et remise du gain.
 * Une remise est définitive et unique (verrou SQL), journalisée avec l'admin qui l'a faite.
 */
#[Layout('components.layouts.admin')]
#[Title('Remise des gains')]
class Index extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Locked]
    public int $campaignId;

    public string $search = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function claim(int $participantId): void
    {
        $this->authorizeAdmin('manage');

        $result = DB::transaction(function () use ($participantId) {
            $participant = Participant::query()
                ->where('campaign_id', $this->campaignId)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $participant->hasSolved()) {
                return 'Ce participant n\'a pas trouvé toutes les lettres.';
            }

            if (! $participant->isActive()) {
                return 'Participation suspendue : remise impossible.';
            }

            if ($participant->hasClaimedPrize()) {
                return 'Gain DÉJÀ REMIS le '.$participant->prize_claimed_at->setTimezone(config('game.display_timezone'))->format('d/m à H:i').'.';
            }

            $participant->forceFill(['prize_claimed_at' => now(), 'prize_claimed_by' => $this->admin()->id])->save();
            $this->audit('prize.claimed', $participant, ['code' => $participant->prizeCode()]);

            return null;
        });

        $result
            ? $this->toast($result, 'error')
            : $this->toast('Gain remis. Le justificatif du participant affiche désormais « Gain déjà remis ».');
    }

    /** Annulation d'une remise faite par erreur (super-admin uniquement, journalisée). */
    public function unclaim(int $participantId): void
    {
        $this->authorizeAdmin('super');

        $participant = Participant::query()->where('campaign_id', $this->campaignId)->findOrFail($participantId);
        $participant->forceFill(['prize_claimed_at' => null, 'prize_claimed_by' => null])->save();

        $this->audit('prize.unclaimed', $participant);
        $this->toast('Remise annulée.');
    }

    public function render()
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);
        $winners = Participant::query()->where('campaign_id', $campaign->id)->whereNotNull('solved_at');
        $term = trim(mb_substr($this->search, 0, 40));

        if ($term !== '') {
            // Code de vérification (6 caractères) ou téléphone / nom
            $byCode = strlen(preg_replace('/[\s-]/', '', $term)) === 6 ? Participant::findByPrizeCode($campaign->id, $term) : null;
            $digits = preg_replace('/\D/', '', $term);

            $winners->where(function ($q) use ($byCode, $term, $digits) {
                $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%");

                if (strlen($digits) >= 4) {
                    $q->orWhere('phone_e164', 'like', "%{$digits}%");
                }

                if ($byCode) {
                    $q->orWhere('id', $byCode->id);
                }
            });
        }

        return view('livewire.admin.prizes.index', [
            'campaign' => $campaign,
            'winners' => $winners->orderByRaw('prize_claimed_at IS NOT NULL')->orderBy('solved_at')->paginate(20),
            'totalWinners' => Participant::query()->where('campaign_id', $campaign->id)->whereNotNull('solved_at')->count(),
            'totalClaimed' => Participant::query()->where('campaign_id', $campaign->id)->whereNotNull('prize_claimed_at')->count(),
            'admins' => Admin::query()->pluck('name', 'id'),
        ]);
    }
}
