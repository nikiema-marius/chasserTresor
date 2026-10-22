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
        $this->get('/s/'.$token)->assertRedirect(route('register', ['jeu' => $campaign->slug]))->assertSessionHas('pending_scan_token', $token);

        // Participant connecté : scan validé, redirection vers le tableau de bord
        $participant = Participant::factory()->for($campaign)->create();
        $this->actingAs($participant, 'participant')
            ->get('/s/'.$token)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('scan_outcome.success', true);

        $this->assertSame(1, $participant->fresh()->letters_count);
    }

    public function test_deux_jeux_en_ligne_le_qr_choisit_le_jeu(): void
    {
        // ORANGE et SIAO en ligne en même temps ; Zeus joue à ORANGE (PIN conservé d'un jeu à l'autre)
        [$orange, $orangeQrs] = $this->campaignWithQrCodes(['name' => 'ORANGE']);
        [$siao, $siaoQrs] = $this->campaignWithQrCodes(['name' => 'SIAO']);
        $zeus = Participant::factory()->for($orange)->create(['phone_e164' => '+22670123456', 'pseudo' => 'Zeus']);
        $zeus->forceFill(['pin_hash' => bcrypt('4826')])->save();
        $token = $siaoQrs[0]->plainToken();

        // Il scanne un QR de SIAO : il reste connecté et arrive sur « Rejoindre SIAO »
        $this->actingAs($zeus, 'participant')
            ->get('/s/'.$token)
            ->assertRedirect(route('join', $siao))
            ->assertSessionHas('pending_scan_token', $token);
        $this->get(route('join', $siao))->assertOk()->assertSee('SIAO')->assertSee('Re-bonjour Zeus');

        // Un clic (règlement accepté) : participation à SIAO, puis validation du QR en attente
        \Livewire\Livewire::actingAs($zeus, 'participant')
            ->test(\App\Livewire\Participant\JoinCampaign::class, ['campaign' => $siao])
            ->call('join')->assertHasErrors('consent_rules')
            ->set('consent_rules', true)
            ->call('join')
            ->assertRedirect(route('scan.link', $token));

        $zeusSiao = Participant::query()->where('campaign_id', $siao->id)->where('phone_e164', '+22670123456')->firstOrFail();
        $this->assertSame('Zeus', $zeusSiao->pseudo);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('4826', $zeusSiao->pin_hash), 'Le PIN est conservé');

        $this->get('/s/'.$token)->assertRedirect(route('dashboard'));
        $this->assertSame(1, $zeusSiao->fresh()->letters_count);
        $this->assertSame(0, $zeus->fresh()->letters_count, 'La partie ORANGE est indépendante');

        // Session sur SIAO, il scanne un QR d'ORANGE : bascule automatique, la lettre va dans ORANGE
        $this->actingAs($zeusSiao, 'participant')->get('/s/'.$orangeQrs[0]->plainToken())->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($zeus, 'participant');
        $this->assertSame(1, $zeus->fresh()->letters_count);

        // Le tableau de bord propose l'autre jeu, et « Continuer ce jeu » rebascule sur SIAO
        $this->get(route('dashboard'))->assertOk()->assertSee('Jeu : ', false)->assertSee('ORANGE')->assertSee('Continuer ce jeu');
        $this->get(route('play', $siao))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($zeusSiao, 'participant');
    }

    public function test_scanner_integre_bascule_sur_le_jeu_du_qr(): void
    {
        [$orange] = $this->campaignWithQrCodes(['name' => 'ORANGE']);
        [, $siaoQrs] = $this->campaignWithQrCodes(['name' => 'SIAO']);
        $participant = Participant::factory()->for($orange)->create();

        \Livewire\Livewire::actingAs($participant, 'participant')
            ->test(\App\Livewire\Participant\Scanner::class)
            ->call('scan', $siaoQrs[0]->scanUrl())
            ->assertRedirect(route('scan.link', $siaoQrs[0]->plainToken()));
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
        $campaign = Campaign::factory()->withWord('MAX IT')->create(['letters_display' => 'ordered']);
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
