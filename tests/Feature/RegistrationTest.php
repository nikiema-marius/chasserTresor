<?php

namespace Tests\Feature;

use App\Contracts\SmsDriver;
use App\Livewire\Participant\Login;
use App\Livewire\Participant\Register;
use App\Models\Campaign;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Support\FakeSmsDriver;
use Tests\TestCase;

/**
 * Inscription, OTP SMS, PIN et reconnexion.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsDriver $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsDriver;
        $this->app->instance(SmsDriver::class, $this->sms);
    }

    private function form(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Register::class)
            ->set('pseudo', 'Awa226')
            ->set('phone', '70 12 34 56')
            ->set('consent_rules', true);
    }

    public function test_inscription_avec_otp(): void
    {
        config(['game.otp.enabled' => true]);
        Campaign::factory()->create();

        $component = $this->form()->call('submit')->assertHasNoErrors()->assertSet('step', 'otp');

        $code = $this->sms->lastCodeFor('+22670123456');
        $this->assertNotNull($code, 'Un SMS avec le code doit être envoyé');
        $this->assertDatabaseMissing('participants', ['phone_e164' => '+22670123456']);

        $component->set('code', $code === '000000' ? '111111' : '000000')->call('verifyOtp')->assertHasErrors('code');
        $component->set('code', $code)->call('verifyOtp')->assertRedirect(route('dashboard'));

        $participant = Participant::query()->where('phone_e164', '+22670123456')->firstOrFail();
        $this->assertNotNull($participant->phone_verified_at);
        $this->assertSame('1.0', $participant->consent_rules_version);
        $this->assertTrue(Auth::guard('participant')->check());
    }

    public function test_un_code_otp_ne_sert_qu_une_fois_et_expire(): void
    {
        config(['game.otp.enabled' => true]);
        Campaign::factory()->create();
        $component = $this->form()->call('submit');
        $code = $this->sms->lastCodeFor('+22670123456');

        $this->travel(6)->minutes();
        $component->set('code', $code)->call('verifyOtp')->assertHasErrors('code');
    }

    public function test_otp_bloque_apres_5_erreurs(): void
    {
        config(['game.otp.enabled' => true]);
        Campaign::factory()->create();
        $component = $this->form()->call('submit');
        $code = $this->sms->lastCodeFor('+22670123456');
        $wrong = $code === '999999' ? '888888' : '999999';

        for ($i = 0; $i < 5; $i++) {
            $component->set('code', $wrong)->call('verifyOtp');
        }

        $component->set('code', $code)->call('verifyOtp')->assertHasErrors('code');
        $this->assertDatabaseMissing('participants', ['phone_e164' => '+22670123456']);
    }

    public function test_limite_d_envoi_de_sms(): void
    {
        config(['game.otp.enabled' => true]);
        Campaign::factory()->create();
        $component = $this->form()->call('submit');

        $component->call('resendOtp')->call('resendOtp')->call('resendOtp')->assertHasErrors('code');
        $this->assertCount(3, $this->sms->sent);
    }

    public function test_un_numero_ne_peut_s_inscrire_qu_une_fois(): void
    {
        $campaign = Campaign::factory()->create();
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456']);

        // Même numéro saisi dans un autre format
        $this->form()->set('phone', '+226 70-12-34-56')->set('pin', '4826')
            ->call('submit')
            ->assertHasErrors('phone');

        $this->assertSame(1, Participant::query()->count());
    }

    public function test_inscription_sans_otp_avec_pin_puis_reconnexion(): void
    {
        Campaign::factory()->create();

        $this->form()->set('pin', '1234')->call('submit')->assertHasErrors('pin');
        $this->form()->set('pin', '4826')->call('submit')->assertRedirect(route('dashboard'));

        $this->assertNull(Participant::query()->first()->phone_verified_at);
        $this->assertCount(0, $this->sms->sent);

        Auth::guard('participant')->logout();

        Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '0000')->call('submit')->assertHasErrors('pin');
        Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '4826')->call('submit')->assertRedirect(route('dashboard'));
    }

    public function test_pin_verrouille_apres_5_erreurs(): void
    {
        $campaign = Campaign::factory()->create();
        $participant = Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456']);
        $participant->forceFill(['pin_hash' => bcrypt('4826')])->save();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '1111')->call('submit');
        }

        Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '4826')->call('submit')
            ->assertHasErrors('pin')->assertNoRedirect();
        $this->assertNotNull($participant->fresh()->pin_locked_until);
    }

    public function test_reconnexion_par_otp(): void
    {
        config(['game.otp.enabled' => true]);
        $campaign = Campaign::factory()->create();
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456']);

        $component = Livewire::test(Login::class)->set('phone', '70 12 34 56')->call('submit')->assertSet('step', 'otp');
        $component->set('code', $this->sms->lastCodeFor('+22670123456'))->call('verifyOtp')->assertRedirect(route('dashboard'));
    }

    public function test_un_compte_se_connecte_et_voit_les_autres_jeux_en_ligne(): void
    {
        $orange = Campaign::factory()->create(['name' => 'ORANGE']);
        $account = Participant::factory()->for($orange)->create(['phone_e164' => '+22670000000']);
        $account->forceFill(['pin_hash' => bcrypt('4826')])->save();
        $siao = Campaign::factory()->create(['name' => 'SIAO']);

        // Connexion avec le PIN habituel, sans réinscription
        Livewire::test(Login::class)
            ->set('phone', '70000000')->set('pin', '4826')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($account, 'participant');

        // Le tableau de bord propose de rejoindre l'autre jeu en ligne
        $this->get(route('dashboard'))->assertOk()->assertSee('Rejoindre ce jeu')->assertSee(route('join', $siao), false);
    }

    public function test_connexion_sur_le_jeu_du_qr_scanne(): void
    {
        [$orange] = [Campaign::factory()->create(['name' => 'ORANGE'])];
        $siao = Campaign::factory()->create(['name' => 'SIAO']);
        $qr = \App\Models\QrCode::factory()->forLetter($siao->letters->first())->create();
        $inOrange = Participant::factory()->for($orange)->create(['phone_e164' => '+22670000000']);
        $inSiao = Participant::factory()->for($siao)->create(['phone_e164' => '+22670000000']);
        foreach ([$inOrange, $inSiao] as $p) {
            $p->forceFill(['pin_hash' => bcrypt('4826')])->save();
        }

        // QR de SIAO scanné avant connexion : on est connecté sur la participation SIAO
        $this->get('/s/'.$qr->plainToken())->assertRedirect(route('register', ['jeu' => $siao->slug]));
        Livewire::test(Login::class)->set('phone', '70000000')->set('pin', '4826')->call('submit')
            ->assertRedirect(route('scan.link', $qr->plainToken()));

        $this->assertAuthenticatedAs($inSiao, 'participant');
    }

    public function test_inscription_refusee_si_le_numero_a_deja_un_compte(): void
    {
        $old = Campaign::factory()->create(['name' => 'ORANGE']);
        Participant::factory()->for($old)->create(['phone_e164' => '+22670123456']);
        $siao = Campaign::factory()->create(['name' => 'SIAO']);

        // Deux jeux en ligne : il faut d'abord choisir le jeu
        $component = $this->form()->set('pin', '4826')
            ->assertSee('Choisis ton jeu')
            ->call('submit')
            ->assertHasErrors('chosenCampaignId');

        // Puis le numéro déjà connu est refusé : il faut se connecter
        $component->set('chosenCampaignId', $siao->id)
            ->call('submit')
            ->assertHasErrors('phone')
            ->assertSee('déjà un compte');

        $this->assertSame(1, Participant::query()->count());
    }

    public function test_numero_inconnu(): void
    {
        Campaign::factory()->create();

        Livewire::test(Login::class)
            ->set('phone', '70000000')->set('pin', '4826')
            ->call('submit')
            ->assertHasErrors('phone')
            ->assertSee('Créer mon compte');
    }

    public function test_validations_du_formulaire(): void
    {
        Campaign::factory()->create();

        // Champs vides ou invalides : pseudo, téléphone, code secret, règlement
        Livewire::test(Register::class)
            ->set('phone', '12')
            ->set('pseudo', '<script>')
            ->call('submit')
            ->assertHasErrors(['phone', 'pseudo', 'pin', 'consent_rules']);

        // Un pseudo trop court est refusé
        Livewire::test(Register::class)->set('pseudo', 'A')->call('submit')->assertHasErrors('pseudo');
    }

    public function test_formulaire_pseudo_telephone_code_secret(): void
    {
        Campaign::factory()->create();

        Livewire::test(Register::class)
            ->assertSee('Pseudo')
            ->assertSee('Numéro de téléphone')
            ->assertSee('Code secret (4 chiffres)')
            ->assertDontSee('Prénom')
            ->assertDontSee('Confirmation')
            ->set('pseudo', 'Zeus_226')->set('phone', '70 12 34 56')->set('pin', '4826')->set('consent_rules', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $participant = Participant::query()->firstOrFail();
        $this->assertSame('Zeus_226', $participant->pseudo);
        $this->assertTrue($participant->age_confirmed, 'L\'âge est confirmé par la case du règlement');
    }

    public function test_qr_scanne_avant_inscription_est_valide_apres(): void
    {
        $campaign = Campaign::factory()->create();
        $qr = \App\Models\QrCode::factory()->forLetter($campaign->letters->first())->create();

        $this->get('/s/'.$qr->plainToken())->assertRedirect(route('register', ['jeu' => $campaign->slug]));

        $this->form()->set('pin', '4826')->call('submit')
            ->assertRedirect(route('scan.link', $qr->plainToken()));
    }
}
