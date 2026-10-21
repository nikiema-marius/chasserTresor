<?php

namespace App\Contracts;

use App\Sms\SmsException;

/**
 * Abstraction d'envoi de SMS. Le driver actif est choisi par SMS_DRIVER
 * (log | null | orange | vonage) dans AppServiceProvider.
 */
interface SmsDriver
{
    /**
     * @param  string  $to  Numéro au format E.164 (+22670123456)
     *
     * @throws SmsException
     */
    public function send(string $to, string $message): void;
}
