<?php

namespace App\Services;

use App\Models\Admin;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification TOTP (Google Authenticator, Microsoft Authenticator, FreeOTP…).
 * Un même code ne peut pas être réutilisé (protection contre le rejeu).
 */
class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /** QR code SVG à scanner dans l'application d'authentification. */
    public function qrSvg(Admin $admin, string $secret): string
    {
        $uri = $this->engine->getQRCodeUrl(config('app.name'), $admin->email, $secret);

        return (new Builder(writer: new SvgWriter, data: $uri, size: 220, margin: 8))
            ->build()
            ->getString();
    }

    public function verify(Admin $admin, #[\SensitiveParameter] string $code, ?string $secret = null): bool
    {
        $secret ??= $admin->two_factor_secret;
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (! $secret || strlen($code) !== 6) {
            return false;
        }

        $key = 'admin-2fa-last:'.$admin->id;
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, Cache::get($key), 1);

        if ($timestamp === false) {
            return false;
        }

        Cache::put($key, $timestamp === true ? $this->engine->getTimestamp() : $timestamp, now()->addMinutes(5));

        return true;
    }
}
