<?php

namespace App\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Normalisation des numéros de téléphone au format E.164 (+22670123456).
 * « 70 12 34 56 », « +226 70123456 », « 0022670123456 » → « +22670123456 »
 */
final class Phone
{
    public static function normalize(string $input): ?string
    {
        $input = trim(mb_substr($input, 0, 30));

        // Préfixe international « 00 » → « + »
        if (str_starts_with($input, '00')) {
            $input = '+'.substr($input, 2);
        }

        try {
            $countries = config('game.phone_countries');
            $phone = new PhoneNumber($input, $countries);

            if (! $phone->isValid() || ! $phone->isOfCountry($countries)) {
                return null;
            }

            return $phone->formatE164();
        } catch (Throwable) {
            return null;
        }
    }

    /** Affichage lisible : +226 70 12 34 56 */
    public static function display(string $e164): string
    {
        try {
            return (new PhoneNumber($e164))->formatInternational();
        } catch (Throwable) {
            return $e164;
        }
    }
}
