<?php

namespace App\Livewire\Admin\Rewards;

use App\Enums\RewardMode;
use App\Enums\WinnerStatus;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Campaign;
use App\Models\RewardDraw;
use App\Models\RewardWinner;
use App\Services\RewardService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Récompenses : générer une proposition (premiers arrivés, tirage, classement),
 * la revoir, puis la valider explicitement. Réservé aux super-admins.
 */
#[Layout('components.layouts.admin')]
#[Title('Récompenses')]
class Index extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public int $campaignId;

    public string $mode = 'first_n';

    public int $winnersCount = 3;

    public string $prizeLabel = '';

    public bool $excludeSuspects = true;

    public string $solvedBefore = '';

    public bool $confirmValidation = false;

    public string $rejectionReason = '';

    public function mount(Campaign $campaign): void
    {
        $this->authorizeAdmin('super');
        $this->campaignId = $campaign->id;
    }

    public function propose(RewardService $rewards): void
    {
        $this->authorizeAdmin('super');
        $this->validate([
            'mode' => ['required', Rule::enum(RewardMode::class)],
            'winnersCount' => ['required', 'integer', 'between:1,500'],
            'prizeLabel' => ['nullable', 'string', 'max:150'],
            'solvedBefore' => ['nullable', 'date'],
        ], attributes: ['winnersCount' => 'nombre de gagnants', 'prizeLabel' => 'lot', 'solvedBefore' => 'date limite']);

        try {
            $rewards->propose(
                $this->campaign(),
                RewardMode::from($this->mode),
                $this->winnersCount,
                $this->prizeLabel ?: null,
                [
                    'exclude_suspects' => $this->excludeSuspects,
                    'solved_before' => $this->solvedBefore
                        ? Carbon::parse($this->solvedBefore, config('game.display_timezone'))->utc()->toDateTimeString()
                        : null,
                ],
                $this->admin(),
            );
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->toast('Proposition générée : vérifiez la liste puis validez-la.');
    }

    public function validateDraw(int $drawId, RewardService $rewards): void
    {
        $this->authorizeAdmin('super');

        if (! $this->confirmValidation) {
            $this->addError('confirmValidation', 'Cochez la case de confirmation.');

            return;
        }

        try {
            $rewards->validate($this->draw($drawId), $this->admin());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->confirmValidation = false;
        $this->toast('Attribution validée.');
    }

    public function cancelDraw(int $drawId, RewardService $rewards): void
    {
        $this->authorizeAdmin('super');

        try {
            $rewards->cancel($this->draw($drawId), $this->admin());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function setWinnerStatus(int $winnerId, string $status, RewardService $rewards): void
    {
        $this->authorizeAdmin('super');

        $winner = RewardWinner::query()
            ->whereHas('draw', fn ($q) => $q->where('campaign_id', $this->campaignId))
            ->findOrFail($winnerId);

        $target = WinnerStatus::from($status);

        if ($target === WinnerStatus::Rejected) {
            $this->validate(['rejectionReason' => ['required', 'string', 'max:255']], attributes: ['rejectionReason' => 'motif']);
        }

        $rewards->setWinnerStatus($winner, $target, $this->admin(), $this->rejectionReason ?: null);
        $this->reset('rejectionReason');
    }

    public function render(RewardService $rewards)
    {
        $campaign = $this->campaign();

        return view('livewire.admin.rewards.index', [
            'campaign' => $campaign,
            'eligibleCount' => $rewards->eligibleQuery($campaign, ['exclude_suspects' => $this->excludeSuspects])->count(),
            'draws' => RewardDraw::query()
                ->where('campaign_id', $campaign->id)
                ->with(['winners.participant', 'creator', 'validator'])
                ->latest('id')
                ->get(),
        ]);
    }

    private function campaign(): Campaign
    {
        return Campaign::query()->findOrFail($this->campaignId);
    }

    private function draw(int $id): RewardDraw
    {
        return RewardDraw::query()->where('campaign_id', $this->campaignId)->findOrFail($id);
    }
}
