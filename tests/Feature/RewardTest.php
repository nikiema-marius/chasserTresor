<?php

namespace Tests\Feature;

use App\Enums\RewardMode;
use App\Enums\WinnerStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Participant;
use App\Services\RewardService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attribution des récompenses : jamais automatique, toujours validée par un super-admin.
 */
class RewardTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaign = Campaign::factory()->create();
        $this->admin = Admin::factory()->superAdmin()->create();
    }

    /** Participant ayant trouvé le mot $minutesAfterStart minutes après son inscription. */
    private function solver(string $solvedAt, int $minutesAfterRegistration): Participant
    {
        $participant = Participant::factory()->for($this->campaign)->create();
        $solved = now()->parse($solvedAt);
        $participant->forceFill([
            'created_at' => $solved->copy()->subMinutes($minutesAfterRegistration),
            'completed_at' => $solved,
            'solved_at' => $solved,
        ])->save();

        return $participant;
    }

    private function propose(RewardMode $mode, int $count, array $criteria = []): \App\Models\RewardDraw
    {
        return app(RewardService::class)->propose($this->campaign, $mode, $count, 'Lot test', $criteria, $this->admin);
    }

    public function test_premiers_arrives(): void
    {
        $late = $this->solver('2026-11-02 10:00:00', 5);
        $first = $this->solver('2026-11-01 09:00:00', 300);
        $second = $this->solver('2026-11-01 09:00:00.500', 60);
        Participant::factory()->for($this->campaign)->create(); // n'a pas trouvé

        $draw = $this->propose(RewardMode::FirstN, 2);

        $this->assertSame([$first->id, $second->id], $draw->winners->pluck('participant_id')->all());
        $this->assertSame(WinnerStatus::Proposed, $draw->winners->first()->status);
        $this->assertNotContains($late->id, $draw->winners->pluck('participant_id'));
    }

    public function test_classement_par_temps(): void
    {
        $slow = $this->solver('2026-11-01 09:00:00', 300);
        $fast = $this->solver('2026-11-02 10:00:00', 5);

        $draw = $this->propose(RewardMode::Ranking, 1);

        $this->assertSame($fast->id, $draw->winners->first()->participant_id);
        $this->assertNotSame($slow->id, $draw->winners->first()->participant_id);
    }

    public function test_tirage_au_sort_reproductible(): void
    {
        foreach (range(1, 10) as $i) {
            $this->solver('2026-11-01 09:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00', 30);
        }

        $draw = $this->propose(RewardMode::Draw, 3);

        $this->assertCount(3, $draw->winners);
        $this->assertSame(64, strlen($draw->seed));
        $this->assertCount(10, $draw->eligibility['eligible_ids']);

        // Vérification indépendante à partir de la graine et de la liste publiée
        $recomputed = app(RewardService::class)->drawIds(collect($draw->eligibility['eligible_ids']), 3, $draw->seed);
        $this->assertSame($recomputed->all(), $draw->winners->pluck('participant_id')->all());
    }

    public function test_suspects_exclus_et_un_seul_lot_par_participant(): void
    {
        $suspect = $this->solver('2026-11-01 08:00:00', 30);
        $suspect->forceFill(['suspicion_score' => 50])->save();
        $honest = $this->solver('2026-11-01 09:00:00', 30);
        $other = $this->solver('2026-11-01 10:00:00', 30);

        $first = $this->propose(RewardMode::FirstN, 1);
        $this->assertSame($honest->id, $first->winners->first()->participant_id);

        $second = $this->propose(RewardMode::FirstN, 5);
        $this->assertSame([$other->id], $second->winners->pluck('participant_id')->all());

        $third = $this->propose(RewardMode::FirstN, 1, ['exclude_suspects' => false]);
        $this->assertSame($suspect->id, $third->winners->first()->participant_id);
    }

    public function test_validation_explicite_par_super_admin(): void
    {
        $this->solver('2026-11-01 09:00:00', 30);
        $draw = $this->propose(RewardMode::FirstN, 1);

        try {
            app(RewardService::class)->validate($draw, Admin::factory()->create());
            $this->fail('Un admin simple ne doit pas pouvoir valider.');
        } catch (DomainException) {
        }

        app(RewardService::class)->validate($draw, $this->admin);

        $this->assertSame('validated', $draw->fresh()->status->value);
        $this->assertSame(WinnerStatus::Validated, $draw->winners()->first()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'reward.validated']);

        $this->expectException(DomainException::class);
        app(RewardService::class)->validate($draw->fresh(), $this->admin);
    }

    public function test_aucun_admissible(): void
    {
        $this->expectException(DomainException::class);
        $this->propose(RewardMode::Draw, 1);
    }
}
