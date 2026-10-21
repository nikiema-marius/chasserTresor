<?php

namespace App\Exceptions;

use App\Enums\ScanResult;
use RuntimeException;

/**
 * Levée dans la transaction de scan pour l'annuler avec un motif précis.
 */
class ScanRejected extends RuntimeException
{
    /** @param int|null $retryAfter secondes à attendre avant un nouveau scan (refus « trop rapide ») */
    public function __construct(public readonly ScanResult $result, public readonly ?int $retryAfter = null)
    {
        parent::__construct($result->value);
    }
}
