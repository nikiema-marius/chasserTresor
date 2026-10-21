<?php

namespace Tests\Feature;

use App\Livewire\Admin\Campaigns\Form;
use App\Livewire\Admin\Campaigns\Index as CampaignsIndex;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\QrCodes\Index as QrIndex;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * Espace d'administration : accès, rôles, campagnes, QR codes, exports.
 */
class AdminTest extends TestCase
{
    use GameHelpers, RefreshDatabase;

    /** Super-admin avec 2FA activée et validée pour la session. */
    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->superAdmin()->create();
        $admin->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        $this->withSession(['admin_2fa_passed' => true]);

        return $admin;
    }

    public function test_acces_refuse_aux_visiteurs_et_participants(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $participant = Participant::factory()->create();
        $this->actingAs($participant, 'participant')->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_connexion_admin(): void
    {
        Admin::factory()->create(['email' => 'chef@example.com', 'password' => 'secret-tres-long-1']);

        Livewire::test(Login::class)
            ->set('email', 'chef@example.com')->set('password', 'mauvais')
            ->call('authenticate')->assertHasErrors('email');

        Livewire::test(Login::class)
            ->set('email', 'CHEF@example.com')->set('password', 'secret-tres-long-1')
            ->call('authenticate')->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.login_failed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.login']);
    }

    public function test_connexion_admin_limitee_a_5_essais(): void
    {
        Admin::factory()->create(['email' => 'chef@example.com', 'password' => 'secret-tres-long-1']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', 'chef@example.com')->set('password', 'x')->call('authenticate');
        }

        Livewire::test(Login::class)
            ->set('email', 'chef@example.com')->set('password', 'secret-tres-long-1')
            ->call('authenticate')
            ->assertHasErrors('email')
            ->assertNoRedirect();
    }

    public function test_2fa_desactivee_par_defaut(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    }

    public function test_super_admin_sans_2fa_doit_l_activer(): void
    {
        config(['game.admin_2fa_required' => true]);
        $admin = Admin::factory()->superAdmin()->create();

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.2fa.setup'));
        $this->actingAs($admin, 'admin')->get(route('admin.2fa.setup'))->assertOk();
    }

    public function test_super_admin_2fa_non_validee_dans_la_session_est_deconnecte(): void
    {
        config(['game.admin_2fa_required' => true]);
        $admin = Admin::factory()->superAdmin()->create();
        $admin->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_compte_desactive_est_deconnecte(): void
    {
        $admin = Admin::factory()->create(['is_active' => false]);

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_roles(): void
    {
        [$campaign] = $this->campaignWithQrCodes();
        $viewer = Admin::factory()->viewer()->create();
        $admin = Admin::factory()->create();

        $this->actingAs($viewer, 'admin')->get(route('admin.participants', $campaign))->assertOk();
        $this->actingAs($viewer, 'admin')->get(route('admin.qr-codes', $campaign))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.qr-codes', $campaign))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.exports.participants', $campaign))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.rewards', $campaign))->assertForbidden();
    }

    public function test_creation_de_campagne_avec_mot_mystere(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(Form::class)
            ->set('name', 'SIAO 2026')
            ->set('word', ' Bogo-lan ')
            ->call('save')
            ->assertHasNoErrors();

        $campaign = Campaign::query()->where('name', 'SIAO 2026')->firstOrFail();
        $this->assertSame(7, $campaign->word_length);
        $this->assertSame('BOGOLAN', $campaign->letters->pluck('letter')->implode(''));
        $this->assertSame('draft', $campaign->status->value);

        // Le mot n'apparaît jamais dans le journal
        $this->assertStringNotContainsString('BOGOLAN', AuditLog::query()->get()->toJson());
    }

    public function test_expression_avec_espace_et_generation_automatique_des_qr(): void
    {
        Livewire::actingAs(Admin::factory()->create(), 'admin')
            ->test(Form::class)
            ->set('name', 'Jour 1')
            ->set('word', 'Max It')
            ->assertSee('10 QR codes')            // aperçu en direct : 5 lettres × 2
            ->call('save')
            ->assertHasNoErrors();

        $campaign = Campaign::query()->where('name', 'Jour 1')->firstOrFail();

        $this->assertSame('MAX IT', $campaign->mystery_word);
        $this->assertSame(5, $campaign->word_length);
        $this->assertSame([3, 2], $campaign->wordLengths());
        $this->assertSame('MAXIT', $campaign->letters->pluck('letter')->implode(''));
        $this->assertSame(10, $campaign->qrCodes()->count());
        $this->assertTrue($campaign->letters->every(fn ($l) => $l->qrCodes()->count() === 2));

        // Le participant peut répondre avec ou sans espace
        $this->assertTrue($campaign->checkMysteryWord('max it'));
        $this->assertTrue($campaign->checkMysteryWord('MAXIT'));
    }

    public function test_changer_le_mot_regenere_les_qr(): void
    {
        $admin = Admin::factory()->create();
        $component = Livewire::actingAs($admin, 'admin')->test(Form::class)
            ->set('name', 'Jour 2')->set('word', 'SIAO')->call('save');

        $campaign = Campaign::query()->where('name', 'Jour 2')->firstOrFail();
        $oldIds = $campaign->qrCodes()->pluck('id');
        $this->assertCount(8, $oldIds);

        Livewire::actingAs($admin, 'admin')
            ->test(Form::class, ['campaign' => $campaign])
            ->set('word', 'ORANGE')
            ->set('qrPerLetter', 3)
            ->assertSee('Changer les lettres supprime')
            ->call('save')
            ->assertHasNoErrors();

        $campaign->refresh();
        $this->assertSame('ORANGE', $campaign->letters()->pluck('letter')->implode(''));
        $this->assertSame(18, $campaign->qrCodes()->count());
        $this->assertSame(0, QrCode::query()->whereIn('id', $oldIds)->count());

        // Même lettres, présentation différente : les QR sont conservés
        $ids = $campaign->qrCodes()->pluck('id')->sort()->values();
        Livewire::actingAs($admin, 'admin')->test(Form::class, ['campaign' => $campaign])->set('word', 'orange')->call('save');
        $this->assertEquals($ids, $campaign->qrCodes()->pluck('id')->sort()->values());
    }

    public function test_mot_verrouille_non_modifiable(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $this->scan(Participant::factory()->for($campaign)->create(), $qrs[0]);

        Livewire::actingAs(Admin::factory()->create(), 'admin')
            ->test(Form::class, ['campaign' => $campaign->fresh()])
            ->set('word', 'AUTRE')
            ->call('save')
            ->assertHasErrors('word');

        $this->assertTrue($campaign->fresh()->checkMysteryWord('ARTISAN'));
    }

    public function test_activation_impossible_sans_qr_pour_chaque_lettre(): void
    {
        $campaign = Campaign::factory()->create(['status' => 'draft', 'regulation_md' => 'Règlement']);

        Livewire::actingAs(Admin::factory()->create(), 'admin')
            ->test(CampaignsIndex::class)
            ->assertSee('Pour pouvoir activer')
            ->call('setStatus', $campaign->id, 'active')
            // Le motif du refus est affiché immédiatement
            ->assertDispatched('notify', type: 'error');

        $this->assertSame('draft', $campaign->fresh()->status->value);
    }

    public function test_activer_une_campagne_la_rend_publique(): void
    {
        // Ancienne campagne publique mais clôturée, nouvelle campagne prête
        $old = Campaign::factory()->current()->closed()->create();
        [$new] = $this->campaignWithQrCodes(['status' => 'draft', 'regulation_md' => 'Règlement']);
        $old->makeCurrent();

        $component = Livewire::actingAs(Admin::factory()->create(), 'admin')
            ->test(CampaignsIndex::class)
            ->assertSee('les participants voient « Jeu terminé »', false)
            ->call('setStatus', $new->id, 'active')
            ->assertDispatched('notify', type: 'success');

        $this->assertTrue(Campaign::current()->is($new));
        $this->assertSame('active', $new->fresh()->status->value);
        $this->assertNull($old->fresh()->is_current);
    }

    public function test_generation_et_cycle_de_vie_des_qr(): void
    {
        $campaign = Campaign::factory()->create();
        $component = Livewire::actingAs(Admin::factory()->create(), 'admin')
            ->test(QrIndex::class, ['campaign' => $campaign])
            ->set('perLetter', 3)
            ->call('generateBatch');

        $this->assertSame(21, QrCode::query()->where('campaign_id', $campaign->id)->count());

        $qr = QrCode::query()->first();
        $component->call('disable', $qr->id);
        $this->assertSame('disabled', $qr->fresh()->status->value);

        $component->call('revoke', $qr->id)->call('enable', $qr->id);
        $this->assertSame('revoked', $qr->fresh()->status->value, 'Un QR révoqué ne revient jamais');

        $component->call('regenerate', QrCode::query()->where('status', 'active')->first()->id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'qr.regenerated']);
    }

    public function test_telechargement_des_images(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $admin = Admin::factory()->create();

        $png = $this->actingAs($admin, 'admin')->get(route('admin.qr-codes.image', [$qrs[0], 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $this->actingAs($admin, 'admin')->get(route('admin.qr-codes.image', [$qrs[0], 'svg']))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $pdf = $this->actingAs($admin, 'admin')->get(route('admin.qr-codes.pdf', $campaign));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($admin, 'admin')->get(route('admin.qr-codes.zip', $campaign))->assertOk();
    }

    public function test_export_csv_masque_les_telephones(): void
    {
        [$campaign] = $this->campaignWithQrCodes();
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456', 'first_name' => '=HYPERLINK("x")']);
        $admin = $this->superAdmin();

        $masked = $this->actingAs($admin, 'admin')->get(route('admin.exports.participants', $campaign))->streamedContent();
        $this->assertStringNotContainsString('+22670123456', $masked);
        $this->assertStringContainsString("'=HYPERLINK", $masked, 'Injection de formule neutralisée');

        $full = $this->actingAs($admin, 'admin')->get(route('admin.exports.participants', [$campaign, 'complet' => 1]))->streamedContent();
        $this->assertStringContainsString('+22670123456', $full);

        $this->assertSame(2, AuditLog::query()->where('event', 'export.participants')->count());
    }

    public function test_tableau_de_bord_statistiques(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();
        $this->collectAll($participant, $qrs);
        Participant::factory()->for($campaign)->create();

        $stats = app(\App\Services\StatsService::class)->forCampaign($campaign);

        $this->assertSame(2, $stats['registered']);
        $this->assertSame(1, $stats['active']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(7, $stats['scans_valid']);
        $this->assertSame(50.0, $stats['conversion']['active']);

        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('Total inscrits')->assertSee('Évolution des participations');
    }

    public function test_toutes_les_pages_admin_s_affichent(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();
        $this->scan($participant, $qrs[0]);
        $admin = $this->superAdmin();

        foreach ([
            route('admin.dashboard'), route('admin.campaigns'), route('admin.campaigns.create'),
            route('admin.campaigns.edit', $campaign), route('admin.qr-codes', $campaign),
            route('admin.participants', $campaign), route('admin.participants.show', $participant),
            route('admin.rewards', $campaign), route('admin.audit'), route('admin.admins'),
        ] as $url) {
            $this->actingAs($admin, 'admin')->get($url)->assertOk();
        }
    }
}
