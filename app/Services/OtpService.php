<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpThrottledException;
use App\Jobs\SendSmsJob;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Codes à usage unique envoyés par SMS.
 *
 * - Code à 6 chiffres (CSPRNG), stocké haché, valable 5 minutes
 * - 5 essais maximum par code, puis le code est inutilisable
 * - 3 envois / 15 min et 10 envois / jour par numéro
 * - Un nouvel envoi invalide les codes précédents
 */
class OtpService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws OtpThrottledException
     */
    public function send(string $phone, OtpPurpose $purpose): void
    {
        $config = config('game.otp');
        $windowKey = 'otp-send:'.$phone;
        $dayKey = 'otp-send-day:'.$phone;

        foreach ([[$windowKey, $config['max_sends_per_window']], [$dayKey, $config['max_sends_per_day']]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->audit->log('otp.throttled', properties: ['phone' => $phone]);

                throw new OtpThrottledException(RateLimiter::availableIn($key));
            }
        }

        RateLimiter::hit($windowKey, $config['send_window_minutes'] * 60);
        RateLimiter::hit($dayKey, 86400);

        $length = (int) $config['length'];
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($phone, $purpose, $code, $config) {
            OtpCode::query()
                ->where('phone_e164', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone_e164' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($config['ttl_minutes']),
                'ip' => request()->ip(),
            ]);
        });

        $message = "Votre code SIAO x Orange Money : {$code}. Valable {$config['ttl_minutes']} min. Ne le communiquez a personne.";

        // En développement (driver log), envoi immédiat pour lire le code dans les logs sans worker
        if (config('game.sms.driver') === 'log') {
            SendSmsJob::dispatchSync($phone, $message);
        } else {
            SendSmsJob::dispatch($phone, $message);
        }
    }

    /**
     * Vérifie un code. Chaque échec consomme un essai ; un succès consomme le code.
     */
    public function verify(string $phone, OtpPurpose $purpose, #[\SensitiveParameter] string $code): bool
    {
        return DB::transaction(function () use ($phone, $purpose, $code) {
            $otp = OtpCode::query()
                ->where('phone_e164', $phone)
                ->where('purpose', $purpose)
                ->usable()
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || ! $otp->isUsable()) {
                return false;
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                if ($otp->attempts >= (int) config('game.otp.max_attempts')) {
                    $this->audit->log('otp.locked', $otp, ['phone' => $phone]);
                }

                return false;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }
}
