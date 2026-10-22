<?php

namespace Tests\Feature;

use App\Livewire\Participant\MyData;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * En-têtes de sécurité, contenus admin assainis, RGPD.
 */
class SecurityAndPrivacyTest extends TestCase
{
    use GameHelpers, RefreshDatabase;

    public function test_en_tetes_de_securite(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy');
    }

    public function test_le_markdown_admin_ne_peut_pas_injecter_de_script(): void
    {
        $campaign = Campaign::factory()->create([
            'regulation_md' => "Règle **importante**\n\n<script>alert(1)</script> [lien](javascript:alert(1))",
        ]);

        $html = $this->get(route('regulation', $campaign))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>importante</strong>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_pages_publiques(): void
    {
        Campaign::factory()->create(['regulation_md' => '## Article 1']);

        $this->get('/')->assertOk()->assertSee('La chasse au trésor');
        $this->get('/reglement')->assertOk()->assertSee('Article 1');
        $this->get('/inscription')->assertOk();
        $this->get('/connexion')->assertOk();
        $this->get('/jeu')->assertRedirect(route('register'));
        $this->get('/s/'.str_repeat('a', 32))->assertRedirect(route('home')); // QR inconnu
    }

    public function test_export_de_mes_donnees(): void
    {
        $participant = Participant::factory()->create(['phone_e164' => '+22670123456']);

        $this->actingAs($participant, 'participant')
            ->get(route('my-data.export'))
            ->assertOk()
            ->assertJsonPath('identite.telephone', '+22670123456');
    }

    public function test_suppression_de_mes_donnees(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create(['pseudo' => 'Awa', 'phone_e164' => '+22670123456']);
        $this->scan($participant, $qrs[0]);

        Livewire::actingAs($participant, 'participant')
            ->test(MyData::class)
            ->set('confirmation', 'oui')->call('deleteAccount')->assertHasErrors('confirmation')
            ->set('confirmation', 'SUPPRIMER')->call('deleteAccount')->assertRedirect(route('home'));

        $participant->refresh();
        $this->assertNotNull($participant->anonymized_at);
        $this->assertStringStartsWith('Anonyme', $participant->pseudo);
        $this->assertStringStartsWith('ANON-', $participant->phone_e164);
        $this->assertNull(Scan::query()->where('participant_id', $participant->id)->value('ip'));
        $this->assertSame(1, $participant->letters_count, 'Les statistiques de jeu sont conservées');
    }

    public function test_purge_rgpd_apres_la_duree_de_conservation(): void
    {
        $old = Campaign::factory()->create(['starts_at' => now()->subDays(300), 'ends_at' => now()->subDays(200)]);
        $recent = Campaign::factory()->create();
        $a = Participant::factory()->for($old)->create();
        $b = Participant::factory()->for($recent)->create();

        $this->artisan('game:purge')->assertSuccessful();

        $this->assertNotNull($a->fresh()->anonymized_at);
        $this->assertNull($b->fresh()->anonymized_at);
    }

    public function test_les_secrets_ne_sont_jamais_serialises(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $this->assertArrayNotHasKey('mystery_word', $campaign->toArray());
        $this->assertArrayNotHasKey('token_encrypted', $qrs[0]->toArray());
        $this->assertArrayNotHasKey('pin_hash', $participant->toArray());
        $this->assertArrayNotHasKey('phone_e164', $participant->toArray());
    }
}
