<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Services\MysteryWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * Pages du parcours participant (maquettes) : accueil, tableau de bord, scanner,
 * mot mystère, lettres, historique, profil, FAQ.
 */
class ParticipantPagesTest extends TestCase
{
    use GameHelpers, RefreshDatabase;

    public function test_accueil_avec_logos(): void
    {
        $this->campaignWithQrCodes();

        $this->get('/')
            ->assertOk()
            ->assertSee('Explore • Scanne • Découvre • Gagne')
            ->assertSee('images/orange-money-dark.png')
            ->assertSee('images/siao.jpg')
            ->assertSee('Voir le règlement complet');
    }

    public function test_pages_du_participant(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create(['pseudo' => 'Aminata']);
        $this->scan($participant, $qrs[0]);

        $this->actingAs($participant, 'participant');

        $this->get(route('dashboard'))->assertOk()->assertSee('Bonjour Aminata')->assertSee('Ma collection de lettres')
            ->assertSee('Collecte toutes les lettres pour le découvrir')->assertDontSee('Historique');
        $this->get(route('scanner'))->assertOk()->assertSee('Aligne le QR code dans le cadre');
        $this->get(route('letters'))->assertOk()->assertSee('Mes découvertes');
        $this->get(route('history'))->assertOk()->assertSee('Lettre trouvée');
        $this->get(route('profile'))->assertOk()->assertSee('Mon profil')->assertSee('Déconnexion')->assertSee('1 / 7');
        $this->get(route('faq'))->assertOk()->assertSee('Questions fréquentes');
    }

    public function test_option_saisie_du_mot(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['require_word_entry' => true]);
        $participant = Participant::factory()->for($campaign)->create();

        $this->actingAs($participant, 'participant')->get(route('mystery'))->assertRedirect(route('dashboard'));

        $this->collectAll($participant, $qrs);
        $this->assertNull($participant->fresh()->solved_at, 'Avec l\'option, il faut encore saisir le mot');
        $this->get(route('mystery'))->assertOk()->assertSee('Saisis le mot mystère')->assertSee('7 lettres');

        app(MysteryWordService::class)->submit($participant->fresh(), 'artisan');
        $this->get(route('mystery'))->assertOk()->assertSee('BRAVOOO !')->assertSee('1er');
    }

    public function test_toutes_les_lettres_suffisent_pour_gagner(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create(['pseudo' => 'Awa']);

        foreach (array_slice($qrs, 0, 6) as $qr) {
            $this->scan($participant, $qr);
        }
        $last = $this->scan($participant, $qrs[6]);

        // Pas de saisie : la dernière lettre fait gagner
        $this->assertTrue($last->solved);
        $this->assertStringContainsString('chapiteau Orange Money', $last->message());
        $this->assertNotNull($participant->fresh()->solved_at);

        $participant->refresh();
        $this->actingAs($participant, 'participant')
            ->get(route('mystery'))
            ->assertOk()
            ->assertSee('BRAVOOO !')
            ->assertSee('Le mot mystère était')
            ->assertSee('chapiteau Orange Money')
            ->assertSee($participant->prizeCode())
            ->assertDontSee('Saisis le mot mystère');

        $this->get(route('dashboard'))->assertOk()->assertSee('Afficher mon justificatif');
    }

    public function test_remise_du_gain_au_chapiteau_une_seule_fois(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $winner = Participant::factory()->for($campaign)->create();
        $this->collectAll($winner, $qrs);
        $loser = Participant::factory()->for($campaign)->create();
        $admin = \App\Models\Admin::factory()->create();

        $page = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Admin\Prizes\Index::class, ['campaign' => $campaign])
            // Recherche par code de vérification (insensible à la casse)
            ->set('search', strtolower($winner->fresh()->prizeCode()))
            ->assertSee($winner->pseudo)
            ->call('claim', $winner->id)
            ->assertDispatched('notify', type: 'success');

        $this->assertNotNull($winner->fresh()->prize_claimed_at);
        $this->assertSame($admin->id, $winner->fresh()->prize_claimed_by);

        // Deuxième remise refusée ; un non-gagnant ne peut rien recevoir
        $page->call('claim', $winner->id)->assertDispatched('notify', type: 'error');
        $page->call('claim', $loser->id)->assertDispatched('notify', type: 'error');
        $this->assertNull($loser->fresh()->prize_claimed_at);
        $this->assertSame(1, \App\Models\AuditLog::query()->where('event', 'prize.claimed')->count());

        // L'écran du gagnant affiche désormais « Gain déjà remis »
        $this->actingAs($winner->fresh(), 'participant')->get(route('mystery'))->assertSee('GAIN DÉJÀ REMIS');
    }

    public function test_retour_du_scan_natif_affiche_l_ecran_bravo(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $this->actingAs($participant, 'participant')
            ->followingRedirects()
            ->get('/s/'.$qrs[1]->plainToken())
            ->assertOk()
            ->assertSee('Bravo !')
            ->assertSee('Tu as trouvé une lettre !')
            ->assertSee('Continuer la chasse');
    }
}
