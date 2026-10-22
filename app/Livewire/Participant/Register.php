<?php

namespace App\Livewire\Participant;

use App\Enums\OtpPurpose;
use App\Enums\ParticipantStatus;
use App\Exceptions\OtpThrottledException;
use App\Livewire\Concerns\AuthenticatesParticipant;
use App\Livewire\Concerns\ThrottlesActions;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\QrCode;
use App\Rules\NotWeakPin;
use App\Services\AuditLogger;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inscription : pseudo + téléphone + code secret (4 chiffres) → création du compte.
 * Avec OTP activé (GAME_OTP_ENABLED), le téléphone est vérifié par SMS à la place du code secret.
 */
#[Layout('components.layouts.app')]
#[Title('Inscription')]
class Register extends Component
{
    use AuthenticatesParticipant, ThrottlesActions;

    public string $pseudo = '';

    public string $phone = '';

    public bool $consent_rules = false;

    public bool $consent_marketing = false;

    public string $pin = '';

    public string $code = '';

    // Propriétés verrouillées : non modifiables depuis le navigateur
    #[Locked]
    public string $step = 'form';

    #[Locked]
    public ?string $pendingPhone = null;

    // Jeu imposé (QR scanné, lien ?jeu=…, ou seul jeu en ligne) ; sinon le participant choisit
    #[Locked]
    public ?int $campaignId = null;

    public ?int $chosenCampaignId = null;

    #[Locked]
    public bool $otpRequired = true;

    public function mount(): void
    {
        $this->otpRequired = (bool) config('game.otp.enabled');

        $online = Campaign::online();
        $fixed = null;

        // QR scanné avant l'inscription : c'est lui qui désigne le jeu
        if (($token = session('pending_scan_token')) && ($qr = QrCode::findByToken($token)) && $qr->campaign->isOnline()) {
            $fixed = $qr->campaign;
        }

        $fixed ??= $online->firstWhere('slug', (string) request()->query('jeu'));
        $fixed ??= $online->count() === 1 ? $online->first() : null;

        $this->campaignId = $fixed?->id;
    }

    protected function rules(): array
    {
        $rules = [
            // Pseudo : commence par une lettre ou un chiffre ; puis lettres, chiffres, espaces, . _ ' -
            'pseudo' => ['required', 'string', 'min:2', 'max:30', "regex:/^[\\pL\\pM\\pN][\\pL\\pM\\pN _.'-]*$/u"],
            'phone' => ['required', 'string', 'max:25', 'phone:'.implode(',', config('game.phone_countries'))],
            'consent_rules' => ['accepted'],
            'consent_marketing' => ['boolean'],
        ];

        if (! $this->campaignId) {
            $rules['chosenCampaignId'] = ['required', 'integer', Rule::in(Campaign::online()->pluck('id')->all())];
        }

        if (! $this->otpRequired) {
            $rules['pin'] = ['required', 'digits:'.config('game.pin.length'), new NotWeakPin];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'pseudo' => 'pseudo',
            'phone' => 'numéro de téléphone',
            'consent_rules' => 'acceptation du règlement',
            'pin' => 'code secret',
            'code' => 'code reçu par SMS',
            'chosenCampaignId' => 'jeu',
        ];
    }

    public function submit(OtpService $otp): void
    {
        if (! $this->attemptThrottled('register:'.request()->ip(), 20, 3600)) {
            return;
        }

        $this->validate();
        $campaign = $this->campaign();

        if (! $campaign) {
            return;
        }
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
            // Plusieurs jeux en ligne et aucun imposé : le participant choisit
            'choices' => $this->campaignId ? collect() : Campaign::online(),
        ]);
    }

    /** Jeu de l'inscription : imposé, ou choisi parmi les jeux en ligne. */
    private function campaign(): ?Campaign
    {
        $id = $this->campaignId ?? $this->chosenCampaignId;
        $campaign = $id ? Campaign::query()->find($id) : null;

        if ($campaign && ! $campaign->isOnline()) {
            $this->addError('campaign', 'Les inscriptions à ce jeu sont fermées.');

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
            'pseudo' => Str::squish($this->pseudo),
            'phone_e164' => $phone,
            'consent_rules_at' => now(),
            'consent_rules_version' => $campaign->regulation_version,
            'consent_marketing' => $this->consent_marketing,
            // L'âge minimum est confirmé par la case du règlement (« … et j'ai au moins N ans »)
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

        $this->reset('pin', 'code');
        $this->loginAndRedirect($participant);
    }

    private function deviceId(): ?string
    {
        $id = request()->cookie(config('game.device_cookie'));

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }
}
