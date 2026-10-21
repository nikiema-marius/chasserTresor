<?php

namespace App\Sms;

use App\Contracts\SmsDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Orange SMS API (developer.orange.com — offre « SMS Africa »).
 *
 * [CLÉ API REQUISE] ORANGE_SMS_CLIENT_ID, ORANGE_SMS_CLIENT_SECRET,
 * ORANGE_SMS_SENDER_ADDRESS (tel:+226…), et éventuellement ORANGE_SMS_SENDER_NAME
 * (nom d'expéditeur à faire valider par Orange). Un forfait SMS doit être actif.
 */
class OrangeSmsDriver implements SmsDriver
{
    private const TOKEN_CACHE_KEY = 'sms:orange:access_token';

    public function __construct(private readonly array $config)
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['sender_address'])) {
            throw new SmsException('Configuration Orange SMS incomplète (ORANGE_SMS_*).');
        }
    }

    public function send(string $to, string $message): void
    {
        $sender = $this->config['sender_address'];

        $payload = [
            'outboundSMSMessageRequest' => array_filter([
                'address' => 'tel:'.$to,
                'senderAddress' => $sender,
                'senderName' => $this->config['sender_name'] ?: null,
                'outboundSMSTextMessage' => ['message' => $message],
            ]),
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 500, throw: false)
            ->post($this->config['base_url'].'/outbound/'.rawurlencode($sender).'/requests', $payload);

        // Jeton expiré côté Orange : on l'oublie pour la prochaine tentative du job
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        if (! $response->successful()) {
            throw new SmsException('Orange SMS : échec HTTP '.$response->status());
        }
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
                ->acceptJson()
                ->timeout(10)
                ->post($this->config['token_url'], ['grant_type' => 'client_credentials']);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new SmsException('Orange SMS : impossible d\'obtenir un jeton OAuth (HTTP '.$response->status().').');
            }

            return $response->json('access_token');
        });
    }
}
