<?php

namespace Tests\Feature;

use App\Enums\QrStatus;
use App\Enums\ScanResult;
use App\Enums\ScanSource;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\ParticipantLetter;
use App\Models\QrCode;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * Scénarios critiques du scan (validation serveur).
 */
class ScanTest extends TestCase
{
    use GameHelpers, RefreshDatabase;

    public function test_scan_valide_attribue_la_lettre_et_journalise(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $outcome = $this->scan($participant, $qrs[0]);

        $this->assertSame(ScanResult::Valid, $outcome->result);
        $this->assertSame('A', $outcome->letter->letter);
        $this->assertSame(1, $participant->fresh()->letters_count);
        $this->assertSame(1, $qrs[0]->fresh()->valid_scans_count);
        $this->assertDatabaseHas('scans', ['participant_id' => $participant->id, 'result' => 'valid']);
        $this->assertNotNull($campaign->fresh()->word_locked_at, 'Le mot doit être verrouillé au premier scan valide');
    }

    public function test_scan_repete_du_meme_qr_est_refuse(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $this->scan($participant, $qrs[0]);
        $second = $this->scan($participant, $qrs[0]);

        $this->assertSame(ScanResult::AlreadyOwnedQr, $second->result);
        $this->assertSame(1, ParticipantLetter::query()->count());
        $this->assertSame(1, $qrs[0]->fresh()->valid_scans_count);
    }

    public function test_deux_qr_de_la_meme_position_ne_donnent_qu_une_lettre(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(perLetter: 2);
        $participant = Participant::factory()->for($campaign)->create();

        $this->assertTrue($this->scan($participant, $qrs[0])->isSuccess());
        $this->assertSame(ScanResult::AlreadyOwnedSlot, $this->scan($participant, $qrs[1])->result);
    }

    public function test_les_lettres_repetees_sont_des_positions_distinctes(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        // ARTISAN : A en position 1 et en position 6
        $this->assertTrue($this->scan($participant, $qrs[0])->isSuccess());
        $this->assertTrue($this->scan($participant, $qrs[5])->isSuccess());
        $this->assertSame(['A', 'A'], [$qrs[0]->campaignLetter->letter, $qrs[5]->campaignLetter->letter]);
    }

    public function test_qr_desactive_ou_revoque_est_refuse(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $qrs[0]->update(['status' => QrStatus::Disabled]);
        $qrs[1]->update(['status' => QrStatus::Revoked]);

        $this->assertSame(ScanResult::Disabled, $this->scan($participant, $qrs[0])->result);
        $this->assertSame(ScanResult::Revoked, $this->scan($participant, $qrs[1])->result);
        $this->assertSame(0, $participant->fresh()->letters_count);
    }

    public function test_jeton_invalide_ou_d_une_autre_campagne(): void
    {
        [$campaign] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();
        $foreign = QrCode::factory()->create(); // autre campagne

        $service = app(\App\Services\ScanService::class);

        $this->assertSame(ScanResult::InvalidToken, $service->scan($participant, 'https://exemple.com/s/'.str_repeat('x', 32), ScanSource::InApp)->result);
        $this->assertSame(ScanResult::InvalidToken, $service->scan($participant, 'texte quelconque', ScanSource::InApp)->result);
        $this->assertSame(ScanResult::OtherCampaign, $this->scan($participant, $foreign)->result);
        $this->assertNotNull(Scan::query()->where('result', 'invalid_token')->value('token_fingerprint'));
    }

    public function test_campagne_fermee_non_commencee_ou_hors_horaires(): void
    {
        foreach ([
            [['status' => 'closed'], ScanResult::CampaignClosed],
            [['status' => 'paused'], ScanResult::CampaignClosed],
            [['ends_at' => now()->subMinute()], ScanResult::CampaignClosed],
            [['starts_at' => now()->addHour()], ScanResult::CampaignNotStarted],
            [['daily_open_time' => now()->addHours(2)->format('H:i:s'), 'daily_close_time' => now()->addHours(3)->format('H:i:s')], ScanResult::OutsideHours],
        ] as [$attributes, $expected]) {
            [$campaign, $qrs] = $this->campaignWithQrCodes($attributes);
            $participant = Participant::factory()->for($campaign)->create();

            $this->assertSame($expected, $this->scan($participant, $qrs[0])->result, json_encode($attributes));
        }
    }

    public function test_participant_bloque_ne_peut_pas_scanner(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->blocked()->create();

        $this->assertSame(ScanResult::ParticipantBlocked, $this->scan($participant, $qrs[0])->result);
    }

    public function test_delai_minimal_entre_deux_scans(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['min_seconds_between_scans' => 20]);
        $participant = Participant::factory()->for($campaign)->create();

        $first = $this->scan($participant, $qrs[0]);
        $this->assertTrue($first->isSuccess());
        $this->assertSame(20, $first->retryAfter, 'Le délai est annoncé dès la lettre trouvée');

        $this->travel(8)->seconds();
        $tooFast = $this->scan($participant, $qrs[1]);
        $this->assertSame(ScanResult::TooFast, $tooFast->result);
        $this->assertSame(12, $tooFast->retryAfter);
        $this->assertSame('Patiente encore 12 secondes avant de scanner un autre QR code.', $tooFast->message());

        $this->travel(13)->seconds();
        $this->assertTrue($this->scan($participant, $qrs[1])->isSuccess());
    }

    public function test_mode_premier_arrive(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['validation_mode' => 'first_come']);
        [$alice, $bob] = Participant::factory()->count(2)->for($campaign)->create();

        $this->assertTrue($this->scan($alice, $qrs[0])->isSuccess());
        $this->assertSame(ScanResult::AlreadyClaimed, $this->scan($bob, $qrs[0])->result);
        $this->assertSame(ScanResult::AlreadyOwnedQr, $this->scan($alice, $qrs[0])->result);
    }

    public function test_mode_surcharge_par_qr(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $qrs[0]->update(['validation_mode' => 'first_come']);
        [$alice, $bob] = Participant::factory()->count(2)->for($campaign)->create();

        $this->scan($alice, $qrs[0]);
        $this->assertSame(ScanResult::AlreadyClaimed, $this->scan($bob, $qrs[0])->result);
        $this->assertTrue($this->scan($bob, $qrs[1])->isSuccess(), 'Les autres QR restent multi-participants');
    }

    public function test_toutes_les_lettres_marquent_la_completion(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        foreach (array_slice($qrs, 0, 6) as $qr) {
            $this->assertFalse($this->scan($participant, $qr)->completed);
        }

        $last = $this->scan($participant, $qrs[6]);

        $this->assertTrue($last->completed);
        $this->assertNotNull($participant->fresh()->completed_at);
        $this->assertSame(100, $participant->fresh()->progressPercent());
    }

    public function test_saisie_manuelle_du_code(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $this->assertTrue($this->scan($participant, $qrs[0], ScanSource::ManualCode)->isSuccess());
    }

    public function test_limitation_de_frequence(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        for ($i = 0; $i < 10; $i++) {
            $this->scan($participant, $qrs[0]);
        }

        $this->assertSame(ScanResult::RateLimited, $this->scan($participant, $qrs[1])->result);
    }

    public function test_lien_natif_visiteur_puis_connecte(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $token = $qrs[0]->plainToken();

        // Visiteur : jeton mis de côté, redirection vers l'inscription
        $this->get('/s/'.$token)->assertRedirect(route('register'))->assertSessionHas('pending_scan_token', $token);

        // Participant connecté : scan validé, redirection vers le tableau de bord
        $participant = Participant::factory()->for($campaign)->create();
        $this->actingAs($participant, 'participant')
            ->get('/s/'.$token)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('scan_outcome.success', true);

        $this->assertSame(1, $participant->fresh()->letters_count);
    }

    public function test_compte_d_une_ancienne_campagne_rejoint_le_jeu_en_cours_sans_reinscription(): void
    {
        // Ancienne campagne (démo) avec un participant inscrit (son PIN est conservé)
        $old = Campaign::factory()->create();
        $participant = Participant::factory()->for($old)->create(['phone_e164' => '+22670123456', 'first_name' => 'Zeus']);
        $participant->forceFill(['pin_hash' => bcrypt('4826')])->save();

        // Nouvelle campagne publique « SIAO 2026 » et un de ses QR
        [$new, $qrs] = $this->campaignWithQrCodes(['name' => 'SIAO 2026']);
        $token = $qrs[0]->plainToken();

        // Il scanne un QR du nouveau jeu : il reste connecté et arrive sur « Rejoindre le jeu »
        $this->actingAs($participant, 'participant')
            ->get('/s/'.$token)
            ->assertRedirect(route('join'))
            ->assertSessionHas('pending_scan_token', $token);
        $this->assertAuthenticatedAs($participant, 'participant');
        $this->get(route('join'))->assertOk()->assertSee('SIAO 2026')->assertSee('Re-bonjour Zeus');

        // Un clic (règlement accepté) : nouvelle participation, puis validation du QR en attente
        \Livewire\Livewire::actingAs($participant, 'participant')
            ->test(\App\Livewire\Participant\JoinCampaign::class)
            ->call('join')->assertHasErrors(['consent_rules', 'age_confirmed'])
            ->set('consent_rules', true)->set('age_confirmed', true)
            ->call('join')
            ->assertRedirect(route('scan.link', $token));

        $participation = Participant::query()->where('campaign_id', $new->id)->where('phone_e164', '+22670123456')->firstOrFail();
        $this->assertSame('Zeus', $participation->first_name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('4826', $participation->pin_hash), 'Le PIN est conservé');
        $this->assertSame($new->regulation_version, $participation->consent_rules_version);
        $this->assertAuthenticatedAs($participation, 'participant');

        $this->get('/s/'.$token)->assertRedirect(route('dashboard'));
        $this->assertSame(1, $participation->fresh()->letters_count);
        $this->assertSame(0, $participant->fresh()->letters_count, 'L\'ancienne partie est inchangée');

        // Session encore sur l'ancien compte alors qu'il a déjà rejoint : bascule automatique
        $this->actingAs($participant, 'participant')->get(route('dashboard'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($participation, 'participant');
    }

    public function test_scanner_livewire(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        \Livewire\Livewire::actingAs($participant, 'participant')
            ->test(\App\Livewire\Participant\Scanner::class)
            ->call('scan', $qrs[0]->scanUrl())
            ->assertSet('outcome.success', true)
            ->assertSet('outcome.letter', 'A')
            ->assertDispatched('scan-result')
            ->call('scan', $qrs[0]->scanUrl())
            ->assertSet('outcome.result', 'already_owned_qr');
    }

    public function test_tableau_de_bord_groupe_les_mots_de_l_expression(): void
    {
        $campaign = Campaign::factory()->current()->withWord('MAX IT')->create(['letters_display' => 'ordered']);
        $participant = Participant::factory()->for($campaign)->create();
        $qr = QrCode::factory()->forLetter($campaign->letters()->where('position', 4)->first())->create(); // le I
        $this->scan($participant, $qr);

        \Livewire\Livewire::actingAs($participant, 'participant')
            ->test(\App\Livewire\Participant\Dashboard::class)
            ->assertViewHas('tileGroups', [[null, null, null], ['I', null]]);
    }

    public function test_mode_desordre_les_lettres_ne_bougent_pas(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['letters_display' => 'shuffled']);
        $participant = Participant::factory()->for($campaign)->create();
        $dashboard = fn () => \Livewire\Livewire::actingAs($participant, 'participant')->test(\App\Livewire\Participant\Dashboard::class);

        $this->scan($participant, $qrs[5]); // A (position 6)
        $dashboard()->assertViewHas('tileGroups', [['A', null, null, null, null, null, null]]);

        $this->travel(1)->seconds();
        $this->scan($participant, $qrs[2]); // T : ajouté APRÈS le A, jamais avant
        $dashboard()->assertViewHas('tileGroups', [['A', 'T', null, null, null, null, null]]);
    }

    public function test_le_tableau_de_bord_affiche_la_progression(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['letters_display' => 'ordered']);
        $participant = Participant::factory()->for($campaign)->create();
        $this->scan($participant, $qrs[1]); // R en position 2

        $this->actingAs($participant, 'participant')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 / 7')
            ->assertSee('14%');
    }
}
