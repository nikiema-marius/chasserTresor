<?php

namespace App\Sms;

use App\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * Vonage SMS API (REST, sans SDK).
 *
 * [CLÉ API REQUISE] VONAGE_KEY, VONAGE_SECRET, VONAGE_SMS_FROM.
 */
class VonageSmsDriver implements SmsDriver
{
    public function __construct(private readonly array $config)
    {
        if (empty($config['key']) || empty($config['secret']) || empty($config['from'])) {
            throw new SmsException('Configuration Vonage incomplète (VONAGE_*).');
        }
    }

    public function send(string $to, string $message): void
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->config['key'],
                'api_secret' => $this->config['secret'],
                'from' => $this->config['from'],
                'to' => ltrim($to, '+'),
                'text' => $message,
                'type' => 'unicode',
            ]);

        // Vonage renvoie HTTP 200 même en cas d'erreur : le statut est dans le corps
        if (! $response->successful() || (string) $response->json('messages.0.status') !== '0') {
            throw new SmsException('Vonage : échec d\'envoi (statut '.($response->json('messages.0.status') ?? $response->status()).').');
        }
    }
}
