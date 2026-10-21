<?php

/*
|--------------------------------------------------------------------------
| Configuration du jeu « La chasse au trésor du SIAO »
|--------------------------------------------------------------------------
| Les paramètres métier propres à une campagne (dates, mot mystère, mode de
| validation, tentatives…) sont stockés en base et gérés depuis l'admin.
| Ce fichier ne contient que les réglages techniques globaux.
*/

return [

    // Préfixe des routes d'administration (à personnaliser en production)
    'admin_path' => env('ADMIN_PATH', 'admin'),

    // Proxies de confiance (« * » derrière Cloudflare / load balancer), vide sinon
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Double authentification (TOTP) obligatoire pour les super-admins
    'admin_2fa_required' => (bool) env('ADMIN_2FA_REQUIRED', false),

    // Compte super-admin initial créé par AdminSeeder
    'admin_seed' => [
        'name' => env('ADMIN_SEED_NAME', 'Super Admin'),
        'email' => env('ADMIN_SEED_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

    'otp' => [
        // true : vérification du téléphone par SMS ; false : PIN à 4 chiffres
        'enabled' => (bool) env('GAME_OTP_ENABLED', false),
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,          // essais par code
        'max_sends_per_window' => 3,  // envois par numéro…
        'send_window_minutes' => 15,  // …sur cette fenêtre
        'max_sends_per_day' => 10,
    ],

    'pin' => [
        'length' => 4,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
    ],

    // Pays autorisés pour les numéros de téléphone (ISO 3166-1 alpha-2)
    'phone_countries' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GAME_PHONE_COUNTRIES', 'BF'))
    ))),
    'phone_default_country' => 'BF',

    'qr' => [
        // Longueur du code de saisie manuelle imprimé sous le QR (Crockford base32)
        'manual_code_length' => 10,
        // Nombre de QR générés par défaut pour chaque position du mot
        'default_per_letter' => 2,
    ],

    // Contraintes sur le mot mystère saisi par l'admin
    'word' => [
        'min_length' => 3,
        'max_length' => 20,
    ],

    // Cookie d'identification de l'appareil (indicatif, sert à la détection de multi-comptes)
    'device_cookie' => 'siao_device',

    'scan' => [
        'max_per_minute_participant' => 10,
        // Large : les opérateurs mobiles partagent une même IP entre de nombreux abonnés (CGNAT)
        'max_per_minute_ip' => 120,
    ],

    // Détection de participations suspectes : signalement pour revue humaine,
    // jamais d'exclusion automatique
    'suspicion' => [
        'threshold' => 30,                // score à partir duquel le participant est « suspect »
        'fast_completion_minutes' => 10,  // toutes les lettres en moins de X minutes
        'invalid_burst' => 5,             // jetons invalides en 10 minutes
        'shared_ip_participants' => 15,   // participants distincts sur une même IP en 5 minutes
        'weights' => [
            'shared_device' => 30,
            'fast_completion' => 30,
            'invalid_burst' => 20,
            'shared_ip' => 10,
            'attempts_exhausted' => 10,
        ],
    ],

    // RGPD : durée de conservation après la fin de campagne, puis anonymisation
    'data_retention_days' => (int) env('GAME_DATA_RETENTION_DAYS', 180),

    // Fuseau d'affichage (stockage en UTC)
    'display_timezone' => 'Africa/Ouagadougou',

    'sms' => [
        // log | null | orange | vonage
        'driver' => env('SMS_DRIVER', 'log'),

        // [CLÉ API REQUISE] https://developer.orange.com — API « SMS Africa »
        'orange' => [
            'client_id' => env('ORANGE_SMS_CLIENT_ID'),
            'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
            'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS'),
            'sender_name' => env('ORANGE_SMS_SENDER_NAME'),
            'token_url' => 'https://api.orange.com/oauth/v3/token',
            'base_url' => 'https://api.orange.com/smsmessaging/v1',
        ],

        // [CLÉ API REQUISE] https://dashboard.nexmo.com
        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_SMS_FROM'),
        ],
    ],
];
