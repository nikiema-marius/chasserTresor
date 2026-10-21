<?php

namespace App\Services;

use App\Enums\ScanResult;
use App\Models\CampaignLetter;

/**
 * Résultat d'un scan renvoyé à l'interface.
 */
final readonly class ScanOutcome
{
    public function __construct(
        public ScanResult $result,
        public ?CampaignLetter $letter = null,
        public bool $completed = false,
        public int $lettersCount = 0,
        // Secondes avant le prochain scan autorisé (délai anti-triche entre deux lettres)
        public ?int $retryAfter = null,
        // Toutes les lettres trouvées et partie gagnée (campagne sans saisie du mot)
        public bool $solved = false,
    ) {}

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    public function message(): string
    {
        if ($this->result === ScanResult::TooFast && $this->retryAfter) {
            return "Patiente encore {$this->retryAfter} seconde".($this->retryAfter > 1 ? 's' : '')
                .' avant de scanner un autre QR code.';
        }

        return match (true) {
            $this->solved => 'Bravooo ! Tu as trouvé toutes les lettres : va récupérer ton gain au chapiteau Orange Money !',
            $this->completed => 'Félicitations, tu as toutes les lettres ! Découvre maintenant le mot mystère.',
            default => $this->result->message(),
        };
    }

    /** Données transmises à la vue (via la session lors d'une redirection). */
    public function toFlash(): array
    {
        return [
            'success' => $this->isSuccess(),
            'result' => $this->result->value,
            'message' => $this->message(),
            'letter' => $this->letter?->letter,
            'completed' => $this->completed,
            'retry_after' => $this->retryAfter,
            'solved' => $this->solved,
        ];
    }
}
