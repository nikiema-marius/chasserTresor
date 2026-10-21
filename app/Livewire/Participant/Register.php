<?php

namespace App\Livewire\Participant;

use App\Enums\OtpPurpose;
use App\Enums\ParticipantStatus;
use App\Enums\ScanResult;
use App\Exceptions\OtpThrottledException;
use App\Livewire\Concerns\AuthenticatesParticipant;
use App\Livewire\Concerns\ThrottlesActions;
use App\Models\Campaign;
use App\Models\Participant;
use App\Rules\NotWeakPin;
use App\Services\AuditLogger;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inscription : formulaire → (OTP SMS si activé) → création du compte.
 * Sans OTP, le participant choisit un PIN qui lui servira à se reconnecter.
 */
#[Layout('components.layouts.app')]
#[Title('Inscription')]
class Register extends Component
{
    use AuthenticatesParticipant, ThrottlesActions;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public bool $consent_rules = false;

    public bool $consent_marketing = false;

    public bool $age_confirmed = false;

    public string $pin = '';

    public string $pin_confirmation = '';

    public string $code = '';

    // Propriétés verrouillées : non modifiables depuis le navigateur
    #[Locked]
    public string $step = 'form';

    #[Locked]
    public ?string $pendingPhone = null;

    #[Locked]
    public ?int $campaignId = null;

    #[Locked]
    public bool $otpRequired = true;

    public function mount(): void
    {
        $campaign = Campaign::current();

        $this->campaignId = $campaign?->id;
        $this->otpRequired = $campaign?->otpRequired() ?? true;
    }

    protected function rules(): array
    {
        $name = ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pM\' -]+$/u'];

        $rules = [
            'first_name' => $name,
            'last_name' => $name,
            'phone' => ['required', 'string', 'max:25', 'phone:'.implode(',', config('game.phone_countries'))],
            'consent_rules' => ['accepted'],
            'age_confirmed' => ['accepted'],
            'consent_marketing' => ['boolean'],
        ];

        if (! $this->otpRequired) {
            $rules['pin'] = ['required', 'digits:'.config('game.pin.length'), 'confirmed', new NotWeakPin];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'numéro de téléphone',
            'consent_rules' => 'acceptation du règlement',
            'age_confirmed' => 'confirmation de l\'âge',
            'pin' => 'code PIN',
            'code' => 'code reçu par SMS',
        ];
    }

    public function submit(OtpService $otp): void
    {
        $campaign = $this->campaign();

        if (! $campaign || ! $this->attemptThrottled('register:'.request()->ip(), 20, 3600)) {
            return;
        }

        $this->validate();
        $phone = $this->normalizedPhone();

        if ($phone === null) {
            return;
        }

        // Un seul compte par numéro, tous jeux confondus : on se connecte, on ne se réinscrit pas
        if (Participant::query()->where('phone_e164', $phone)->whereNull('anonymized_at')->exists()) {
            $this->addError('phone', 'Tu as déjà un compte avec ce numéro. Connecte-toi pour jouer.');

            return;
        }

        if (! $this->otpRequired) {
            $this->createParticipant($campaign, $phone, verified: false);

            return;
        }

        try {
            $otp->send($phone, OtpPurpose::Register);
        } catch (OtpThrottledException $e) {
            $this->addError('phone', "Trop de demandes de code. Réessaie dans {$e->retryAfterMinutes()} min.");

            return;
        }

        $this->pendingPhone = $phone;
        $this->step = 'otp';
        $this->code = '';
    }

    public function verifyOtp(OtpService $otp): void
    {
        $campaign = $this->campaign();

        if (! $campaign || $this->step !== 'otp' || $this->pendingPhone === null) {
            return;
        }

        if (! $this->attemptThrottled('otp-verify:'.$this->pendingPhone, 10, 900, 'code')) {
            return;
        }

        $this->validate(['code' => ['required', 'digits:'.config('game.otp.length')]]);

        // Les champs du formulaire sont revalidés : ils ont pu être modifiés côté navigateur
        $this->validate();

        if ($this->normalizedPhone() !== $this->pendingPhone) {
            $this->backToForm();

            return;
        }

        if (! $otp->verify($this->pendingPhone, OtpPurpose::Register, $this->code)) {
            $this->addError('code', 'Code incorrect ou expiré.');

            return;
        }

        $this->createParticipant($campaign, $this->pendingPhone, verified: true);
    }

    public function resendOtp(OtpService $otp): void
    {
        if ($this->step !== 'otp' || $this->pendingPhone === null) {
            return;
        }

        try {
            $otp->send($this->pendingPhone, OtpPurpose::Register);
            session()->flash('status', "Un nouveau code t'a été envoyé.");
        } catch (OtpThrottledException $e) {
            $this->addError('code', "Trop de demandes de code. Réessaie dans {$e->retryAfterMinutes()} min.");
        }
    }

    public function backToForm(): void
    {
        $this->step = 'form';
        $this->pendingPhone = null;
        $this->code = '';
    }

    public function render()
    {
        return view('livewire.participant.register', [
            'campaign' => $this->campaign(),
        ]);
    }

    private function campaign(): ?Campaign
    {
        $campaign = $this->campaignId ? Campaign::query()->find($this->campaignId) : null;

        if (! $campaign || $campaign->availabilityAt(now()) === ScanResult::CampaignClosed) {
            $this->addError('campaign', 'Les inscriptions sont fermées.');

            return null;
        }

        return $campaign;
    }

    private function normalizedPhone(): ?string
    {
        $phone = Phone::normalize($this->phone);

        if ($phone === null) {
            $this->addError('phone', 'Numéro de téléphone invalide.');
        }

        return $phone;
    }

    private function createParticipant(Campaign $campaign, string $phone, bool $verified): void
    {
        $participant = new Participant([
            'campaign_id' => $campaign->id,
            'first_name' => Str::squish($this->first_name),
            'last_name' => Str::squish($this->last_name),
            'phone_e164' => $phone,
            'consent_rules_at' => now(),
            'consent_rules_version' => $campaign->regulation_version,
            'consent_marketing' => $this->consent_marketing,
            'age_confirmed' => true,
            'device_id' => $this->deviceId(),
            'registration_ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);

        $participant->forceFill([
            'status' => ParticipantStatus::Active,
            'phone_verified_at' => $verified ? now() : null,
            'pin_hash' => $this->otpRequired ? null : Hash::make($this->pin),
        ]);

        try {
            $participant->save();
        } catch (UniqueConstraintViolationException) {
            // Double soumission simultanée : la contrainte unique a tranché
            $this->backToForm();
            $this->addError('phone', 'Tu as déjà un compte avec ce numéro. Connecte-toi pour jouer.');

            return;
        }

        app(AuditLogger::class)->log('participant.registered', $participant, [
            'otp_verified' => $verified,
            'marketing' => $this->consent_marketing,
        ], $participant);

        $this->reset('pin', 'pin_confirmation', 'code');
        $this->loginAndRedirect($participant);
    }

    private function deviceId(): ?string
    {
        $id = request()->cookie(config('game.device_cookie'));

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }
}
