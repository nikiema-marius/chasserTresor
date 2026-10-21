<?php

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
| Deux gardes distinctes et étanches :
| - « participant » : joueurs (téléphone + OTP SMS ou PIN)
| - « admin »       : back-office (email + mot de passe)
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'participant'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'participant' => [
            'driver' => 'session',
            'provider' => 'participants',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'participants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Participant::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 30,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
