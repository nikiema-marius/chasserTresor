<?php

namespace App\Livewire\Participant;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpThrottledException;
use App\Livewire\Concerns\AuthenticatesParticipant;
use App\Livewire\Concerns\ThrottlesActions;
use App\Models\Campaign;
use App\Models\Participant;
use App\Services\AuditLogger;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reconnexion d'un participant (autre téléphone, session expirée…) :
 * téléphone + code SMS, ou téléphone + PIN si l'OTP est désactivé.
 */
#[Layout('components.layouts.app')]
#[Title('Me reconnecter')]
class Login extends Component
{
    use AuthenticatesParticipant, ThrottlesActions;

    public string $phone = '';

    public string $pin = '';

    public string $code = '';

    #[Locked]
    public string $step = 'form';

    #[Locked]
    public ?int $participantId = null;

    #[Locked]
    public bool $otpRequired = true;

    // Affiche le bouton « S'inscrire » quand le numéro n'est pas inscrit au jeu en cours
    #[Locked]
    public bool $needsRegistration = false;

    public function mount(): void
    {
        $this->otpRequired = Campaign::current()?->otpRequired() ?? true;
    }

    public function submit(OtpService $otp, AuditLogger $audit): void
    {
        if (! $this->attemptThrottled('participant-login:'.request()->ip(), 30, 3600, 'phone')) {
            return;
        }

        $this->validate([
            'phone' => ['required', 'string', 'max:25'],
            'pin' => $this->otpRequired ? ['nullable'] : ['required', 'digits:'.config('game.pin.length')],
        ], attributes: ['phone' => 'numéro de téléphone', 'pin' => 'code PIN']);

        $phone = Phone::normalize($this->phone);
        $participant = $phone ? $this->findAccount($phone) : null;

        if (! $participant) {
            $this->addError('phone', 'Aucun compte trouvé avec ce numéro.');
            $this->needsRegistration = true;

            return;
        }

        if ($this->otpRequired) {
            try {
                $otp->send($participant->phone_e164, OtpPurpose::Login);
            } catch (OtpThrottledException $e) {
                $this->addError('phone', "Trop de demandes de code. Réessaie dans {$e->retryAfterMinutes()} min.");

                return;
            }

            $this->participantId = $participant->id;
            $this->step = 'otp';

            return;
        }

        $this->loginWithPin($participant, $audit);
    }

    public function verifyOtp(OtpService $otp): void
    {
        $participant = $this->step === 'otp' && $this->participantId
            ? Participant::query()->find($this->participantId)
            : null;

        if (! $participant || ! $this->attemptThrottled('otp-verify:'.$participant->phone_e164, 10, 900, 'code')) {
            return;
        }

        $this->validate(['code' => ['required', 'digits:'.config('game.otp.length')]], attributes: ['code' => 'code reçu par SMS']);

        if (! $otp->verify($participant->phone_e164, OtpPurpose::Login, $this->code)) {
            $this->addError('code', 'Code incorrect ou expiré.');

            return;
        }

        $participant->forceFill(['phone_verified_at' => $participant->phone_verified_at ?? now()])->save();
        $this->loginAndRedirect($participant);
    }

    public function backToForm(): void
    {
        $this->reset('step', 'participantId', 'code');
    }

    public function render()
    {
        return view('livewire.participant.login');
    }

    /**
     * Compte du participant : sa participation au jeu en cours si elle existe,
     * sinon sa participation la plus récente (il rejoindra alors le jeu en cours en un clic).
     */
    private function findAccount(string $phone): ?Participant
    {
        $currentId = Campaign::current()?->id;

        return Participant::query()
            ->where('phone_e164', $phone)
            ->whereNull('anonymized_at')
            ->orderByRaw('campaign_id = ? DESC', [(int) $currentId])
            ->latest('id')
            ->first();
    }

    private function loginWithPin(Participant $participant, AuditLogger $audit): void
    {
        if ($participant->pin_locked_until?->isFuture()) {
            $this->addError('pin', 'Trop d\'essais. Réessaie dans '.max(1, (int) ceil(now()->diffInMinutes($participant->pin_locked_until))).' min.');

            return;
        }

        if (! $participant->pin_hash || ! Hash::check($this->pin, $participant->pin_hash)) {
            $failed = $participant->pin_failed_attempts + 1;
            $locked = $failed >= (int) config('game.pin.max_attempts');

            $participant->forceFill([
                'pin_failed_attempts' => $locked ? 0 : $failed,
                'pin_locked_until' => $locked ? now()->addMinutes((int) config('game.pin.lockout_minutes')) : null,
            ])->save();

            if ($locked) {
                $audit->log('participant.pin_locked', $participant);
            }

            $this->addError('pin', $participant->pin_hash
                ? 'Code PIN incorrect.'
                : 'Ce compte n\'a pas de code PIN. Rends-toi au stand Orange Money.');

            return;
        }

        $participant->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();
        $this->reset('pin');
        $this->loginAndRedirect($participant);
    }
}
