<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse les PIN trop faciles à deviner : chiffres identiques (0000),
 * suites croissantes ou décroissantes (1234, 4321) et quelques classiques.
 */
class NotWeakPin implements ValidationRule
{
    private const BLACKLIST = ['1212', '1004', '2000', '2580', '6969', '1122', '1313'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! preg_match('/^\d+$/', $pin)) {
            return; // la règle « digits » se charge du format
        }

        $digits = array_map('intval', str_split($pin));
        $steps = array_unique(array_map(fn ($a, $b) => $b - $a, array_slice($digits, 0, -1), array_slice($digits, 1)));

        $sameDigits = count(array_unique($digits)) === 1;
        $sequence = count($steps) === 1 && in_array(reset($steps), [1, -1], true);

        if ($sameDigits || $sequence || in_array($pin, self::BLACKLIST, true)) {
            $fail('Code trop facile à deviner : évitez les suites (1234, 4321) et les chiffres répétés (0000). Exemple valide : 4826.');
        }
    }
}
