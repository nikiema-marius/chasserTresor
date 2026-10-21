<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisation des mots (mot mystère admin et propositions des participants) :
 * accents supprimés (É → E), majuscules, tout ce qui n'est pas une lettre A-Z
 * est retiré (espaces, tirets, apostrophes…).
 *
 * «  ar tisan » , « Artisan », « ARTI-SAN » → « ARTISAN »
 */
final class WordNormalizer
{
    public static function normalize(string $value): string
    {
        $ascii = Str::ascii(mb_substr($value, 0, 100));

        return preg_replace('/[^A-Z]/', '', strtoupper($ascii)) ?? '';
    }

    /**
     * Forme d'affichage : majuscules sans accents, mots séparés par un espace.
     * « Max-It 2026 » → « MAX IT », « orange » → « ORANGE »
     */
    public static function display(string $value): string
    {
        $ascii = strtoupper(Str::ascii(mb_substr($value, 0, 100)));

        return trim(preg_replace('/[^A-Z]+/', ' ', $ascii) ?? '');
    }

    /**
     * Comparaison en temps constant de deux mots normalisés.
     */
    public static function equals(string $expected, string $submitted): bool
    {
        $expected = self::normalize($expected);
        $submitted = self::normalize($submitted);

        return $expected !== '' && hash_equals($expected, $submitted);
    }
}
