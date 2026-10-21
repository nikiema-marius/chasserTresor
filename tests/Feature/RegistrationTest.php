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
            ->set('first_name', 'Awa')
            ->set('last_name', 'Ouédraogo')
            ->set('phone', '70 12 34 56')
            ->set('consent_rules', true)
            ->set('age_confirmed', true);
    }

    public function test_inscription_avec_otp(): void
    {
        Campaign::factory()->current()->create(['otp_required' => true]);

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
        Campaign::factory()->current()->create(['otp_required' => true]);
        $component = $this->form()->call('submit');
        $code = $this->sms->lastCodeFor('+22670123456');

        $this->travel(6)->minutes();
        $component->set('code', $code)->call('verifyOtp')->assertHasErrors('code');
    }

    public function test_otp_bloque_apres_5_erreurs(): void
    {
        Campaign::factory()->current()->create(['otp_required' => true]);
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
        Campaign::factory()->current()->create(['otp_required' => true]);
        $component = $this->form()->call('submit');

        $component->call('resendOtp')->call('resendOtp')->call('resendOtp')->assertHasErrors('code');
        $this->assertCount(3, $this->sms->sent);
    }

    public function test_un_numero_ne_peut_s_inscrire_qu_une_fois(): void
    {
        $campaign = Campaign::factory()->current()->create(['otp_required' => false]);
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456']);

        // Même numéro saisi dans un autre format
        $this->form()->set('phone', '+226 70-12-34-56')->set('pin', '4826')->set('pin_confirmation', '4826')
            ->call('submit')
            ->assertHasErrors('phone');

        $this->assertSame(1, Participant::query()->count());
    }

    public function test_inscription_sans_otp_avec_pin_puis_reconnexion(): void
    {
        Campaign::factory()->current()->create(['otp_required' => false]);

        $this->form()->set('pin', '1234')->set('pin_confirmation', '1234')->call('submit')->assertHasErrors('pin');
        $this->form()->set('pin', '4826')->set('pin_confirmation', '4826')->call('submit')->assertRedirect(route('dashboard'));

        $this->assertNull(Participant::query()->first()->phone_verified_at);
        $this->assertCount(0, $this->sms->sent);

        Auth::guard('participant')->logout();

        Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '0000')->call('submit')->assertHasErrors('pin');
        Livewire::test(Login::class)->set('phone', '70123456')->set('pin', '4826')->call('submit')->assertRedirect(route('dashboard'));
    }

    public function test_pin_verrouille_apres_5_erreurs(): void
    {
        $campaign = Campaign::factory()->current()->create(['otp_required' => false]);
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
        $campaign = Campaign::factory()->current()->create(['otp_required' => true]);
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670123456']);

        $component = Livewire::test(Login::class)->set('phone', '70 12 34 56')->call('submit')->assertSet('step', 'otp');
        $component->set('code', $this->sms->lastCodeFor('+22670123456'))->call('verifyOtp')->assertRedirect(route('dashboard'));
    }

    public function test_compte_d_un_jeu_precedent_se_connecte_sans_reinscription(): void
    {
        $old = Campaign::factory()->create();
        $account = Participant::factory()->for($old)->create(['phone_e164' => '+22670000000']);
        $account->forceFill(['pin_hash' => bcrypt('4826')])->save();
        Campaign::factory()->current()->create(['name' => 'SIAO 2026', 'otp_required' => false]);

        // Connexion avec le PIN habituel, puis le tableau de bord mène à « Rejoindre le jeu »
        Livewire::test(Login::class)
            ->set('phone', '70000000')->set('pin', '4826')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($account, 'participant');
        $this->get(route('dashboard'))->assertRedirect(route('join'));
    }

    public function test_inscription_refusee_si_le_numero_a_deja_un_compte(): void
    {
        $old = Campaign::factory()->create();
        Participant::factory()->for($old)->create(['phone_e164' => '+22670123456']);
        Campaign::factory()->current()->create(['otp_required' => false]);

        $this->form()->set('pin', '4826')->set('pin_confirmation', '4826')
            ->call('submit')
            ->assertHasErrors('phone')
            ->assertSee('déjà un compte');

        $this->assertSame(1, Participant::query()->count());
    }

    public function test_numero_inconnu(): void
    {
        Campaign::factory()->current()->create(['otp_required' => false]);

        Livewire::test(Login::class)
            ->set('phone', '70000000')->set('pin', '4826')
            ->call('submit')
            ->assertHasErrors('phone')
            ->assertSee('Créer mon compte');
    }

    public function test_validations_du_formulaire(): void
    {
        Campaign::factory()->current()->create(['otp_required' => true]);

        Livewire::test(Register::class)
            ->set('phone', '12')
            ->set('first_name', '<script>')
            ->call('submit')
            ->assertHasErrors(['phone', 'first_name', 'last_name', 'consent_rules', 'age_confirmed']);
    }

    public function test_qr_scanne_avant_inscription_est_valide_apres(): void
    {
        $campaign = Campaign::factory()->current()->create(['otp_required' => false]);
        $qr = \App\Models\QrCode::factory()->forLetter($campaign->letters->first())->create();

        $this->get('/s/'.$qr->plainToken())->assertRedirect(route('register'));

        $this->form()->set('pin', '4826')->set('pin_confirmation', '4826')->call('submit')
            ->assertRedirect(route('scan.link', $qr->plainToken()));
    }
}
