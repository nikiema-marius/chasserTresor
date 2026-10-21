<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Trop de demandes de code OTP pour ce numéro.
 */
class OtpThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Trop de demandes de code.');
    }

    public function retryAfterMinutes(): int
    {
        return max(1, (int) ceil($this->retryAfterSeconds / 60));
    }
}
