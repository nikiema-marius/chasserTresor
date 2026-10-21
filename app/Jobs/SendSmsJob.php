<?php

namespace App\Jobs;

use App\Contracts\SmsDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'un SMS en file d'attente : l'inscription ne bloque pas si l'API SMS est lente.
 */
class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Un OTP expire au bout de 5 minutes : inutile d'insister au-delà
    public int $timeout = 20;

    public function __construct(
        public readonly string $to,
        #[\SensitiveParameter] public readonly string $message,
    ) {}

    public function backoff(): array
    {
        return [5, 20];
    }

    public function handle(SmsDriver $sms): void
    {
        $sms->send($this->to, $this->message);
    }

    public function failed(Throwable $e): void
    {
        // Ne jamais journaliser le contenu (il contient le code OTP)
        Log::channel('security')->error('Échec d\'envoi SMS', [
            'to' => substr($this->to, 0, 6).'…',
            'error' => $e->getMessage(),
        ]);
    }
}
