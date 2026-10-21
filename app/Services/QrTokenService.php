<?php

namespace App\Services;

/**
 * Génération et vérification des jetons de QR codes.
 *
 * - Jeton : 24 octets aléatoires (CSPRNG) encodés en base64url → 32 caractères,
 *   192 bits d'entropie. Ne contient AUCUNE information sur la lettre.
 * - Code manuel : 10 caractères Crockford base32 (50 bits), imprimé sous le QR
 *   pour la saisie à la main quand la caméra est refusée.
 * - En base, seul le SHA-256 sert à la recherche (index unique).
 */
class QrTokenService
{
    // Alphabet Crockford : sans I, L, O, U (évite les confusions de lecture)
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const TOKEN_LENGTH = 32;

    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function isWellFormedToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{'.self::TOKEN_LENGTH.'}$/', $token) === 1;
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateManualCode(): string
    {
        $length = (int) config('game.qr.manual_code_length', 10);
        $code = '';

        // 256 est un multiple de 32 : le masque & 31 donne une distribution uniforme
        foreach (str_split(random_bytes($length)) as $byte) {
            $code .= self::CROCKFORD[ord($byte) & 31];
        }

        return $code;
    }

    /**
     * Normalise une saisie manuelle : majuscules, sans tirets ni espaces,
     * corrige les confusions courantes (O → 0, I/L → 1). Retourne null si invalide.
     */
    public function normalizeManualCode(string $input): ?string
    {
        $code = strtoupper(preg_replace('/[\s-]+/', '', mb_substr($input, 0, 40)) ?? '');
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        $length = (int) config('game.qr.manual_code_length', 10);

        if (strlen($code) !== $length || strspn($code, self::CROCKFORD) !== $length) {
            return null;
        }

        return $code;
    }

    public function hashManualCode(string $normalizedCode): string
    {
        // Préfixe de domaine : un code manuel ne peut jamais collisionner avec un jeton
        return hash('sha256', 'manual:'.$normalizedCode);
    }

    /** Affichage lisible : ABCDE-FGHJK */
    public function formatManualCode(string $code): string
    {
        return implode('-', str_split($code, 5));
    }

    /** URL encodée dans le QR (ouvrable par l'appareil photo natif). */
    public function scanUrl(string $token): string
    {
        return url('/s/'.$token);
    }

    /**
     * Extrait le jeton d'une valeur lue par le scanner : URL complète du jeu
     * ou jeton brut. Retourne null si le format ne correspond pas.
     */
    public function extractToken(string $scanned): ?string
    {
        $scanned = trim(mb_substr($scanned, 0, 500));

        if ($this->isWellFormedToken($scanned)) {
            return $scanned;
        }

        if (preg_match('#/s/([A-Za-z0-9_-]{'.self::TOKEN_LENGTH.'})(?:[/?\#]|$)#', $scanned, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
