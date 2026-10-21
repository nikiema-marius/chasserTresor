<?php

namespace Tests\Support;

use App\Contracts\SmsDriver;

/**
 * Driver SMS de test : mémorise les messages au lieu de les envoyer.
 */
class FakeSmsDriver implements SmsDriver
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    /** Dernier code OTP envoyé à ce numéro. */
    public function lastCodeFor(string $to): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['to'] === $to && preg_match('/\b(\d{6})\b/', $sms['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
