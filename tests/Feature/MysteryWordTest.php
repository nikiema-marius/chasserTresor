<?php

namespace Tests\Feature;

use App\Enums\MysteryResult;
use App\Livewire\Participant\MysteryWordForm;
use App\Models\Participant;
use App\Services\MysteryWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * Soumission du mot mystère.
 */
class MysteryWordTest extends TestCase
{
    use GameHelpers, RefreshDatabase;

    private function submit(Participant $participant, string $word): MysteryResult
    {
        $this->travel(11)->seconds(); // au-delà de la limite d'1 proposition / 10 s

        return app(MysteryWordService::class)->submit($participant->fresh(), $word)[0];
    }

    private function completedParticipant(array $campaignAttributes = []): Participant
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes($campaignAttributes + ['require_word_entry' => true]);
        $participant = Participant::factory()->for($campaign)->create();
        $this->collectAll($participant, $qrs);

        return $participant->fresh();
    }

    public function test_mot_correct_insensible_casse_espaces_accents(): void
    {
        $participant = $this->completedParticipant();

        $this->assertSame(MysteryResult::Correct, $this->submit($participant, '  ar tisàn '));

        $participant->refresh();
        $this->assertNotNull($participant->solved_at);
        $this->assertSame(1, $participant->word_attempts);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mystery.solved', 'subject_id' => $participant->id]);
    }

    public function test_mot_incorrect_consomme_un_essai(): void
    {
        $participant = $this->completedParticipant();

        $this->assertSame(MysteryResult::Incorrect, $this->submit($participant, 'BOGOLAN'));
        $this->assertSame(4, $participant->fresh()->remainingAttempts());
        $this->assertNull($participant->fresh()->solved_at);
    }

    public function test_tentatives_limitees(): void
    {
        $participant = $this->completedParticipant(['max_word_attempts' => 2]);

        $this->assertSame(MysteryResult::Incorrect, $this->submit($participant, 'UN'));
        $this->assertSame(MysteryResult::Incorrect, $this->submit($participant, 'DEUX'));
        $this->assertSame(MysteryResult::NoAttemptsLeft, $this->submit($participant, 'ARTISAN'));
        $this->assertNull($participant->fresh()->solved_at);
        $this->assertArrayHasKey('attempts_exhausted', $participant->fresh()->suspicion_flags);

        // L'admin accorde un essai supplémentaire
        $participant->increment('bonus_word_attempts');
        $this->assertSame(MysteryResult::Correct, $this->submit($participant, 'ARTISAN'));
    }

    public function test_impossible_sans_toutes_les_lettres(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();
        $this->scan($participant, $qrs[0]);

        $this->assertSame(MysteryResult::NotCompleted, $this->submit($participant, 'ARTISAN'));
        $this->assertSame(0, $participant->fresh()->word_attempts);
    }

    public function test_impossible_campagne_fermee(): void
    {
        $participant = $this->completedParticipant();
        $participant->campaign->update(['status' => 'closed']);

        $this->assertSame(MysteryResult::CampaignClosed, $this->submit($participant, 'ARTISAN'));
    }

    public function test_deja_trouve(): void
    {
        $participant = $this->completedParticipant();
        $this->submit($participant, 'ARTISAN');

        $this->assertSame(MysteryResult::AlreadySolved, $this->submit($participant, 'ARTISAN'));
        $this->assertSame(1, $participant->fresh()->word_attempts);
    }

    public function test_saisie_vide_ne_consomme_pas_d_essai(): void
    {
        $participant = $this->completedParticipant();

        $this->assertSame(MysteryResult::Empty, $this->submit($participant, ' 123 - '));
        $this->assertSame(0, $participant->fresh()->word_attempts);
    }

    public function test_formulaire_livewire(): void
    {
        $participant = $this->completedParticipant();

        Livewire::actingAs($participant, 'participant')
            ->test(MysteryWordForm::class)
            ->set('word', 'artisan')
            ->call('submit')
            ->assertSet('success', true)
            ->assertDispatched('mystery-solved');
    }

    public function test_le_mot_n_apparait_jamais_dans_les_pages(): void
    {
        $participant = $this->completedParticipant(['letters_display' => 'shuffled']);

        $html = $this->actingAs($participant, 'participant')->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('ARTISAN', $html);
    }
}
