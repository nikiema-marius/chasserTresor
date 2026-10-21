<?php

/*
 * Processus « client » pour les tests de concurrence (ConcurrencyTest).
 * Démarre l'application, attend l'instant de départ commun puis exécute
 * UNE action, et affiche le résultat sur la sortie standard.
 *
 * Usage : php concurrent_worker.php <scan|word> <participant_id> <payload> <start_at_microtime>
 */

use App\Enums\ScanSource;
use App\Models\Participant;
use App\Services\MysteryWordService;
use App\Services\ScanService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $mode, $participantId, $payload, $startAt] = $argv;

$participant = Participant::query()->findOrFail((int) $participantId);

// Signale si le processus était prêt avant l'instant de départ (sinon la course n'est pas réelle)
fwrite(STDERR, microtime(true) < (float) $startAt ? 'ready-in-time' : 'late');

// Barrière : tous les processus agissent au même instant
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

echo match ($mode) {
    'scan' => app(ScanService::class)->scan($participant, $payload, ScanSource::InApp)->result->value,
    'word' => app(MysteryWordService::class)->submit($participant, $payload)[0]->value,
};
