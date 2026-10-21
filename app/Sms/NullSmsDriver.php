<?php

namespace App\Sms;

use App\Contracts\SmsDriver;

/**
 * N'envoie rien (OTP désactivé ou tests).
 */
class NullSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): void
    {
        //
    }
}
