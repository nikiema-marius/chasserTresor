<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MysteryAttempt;
use App\Models\Participant;
use App\Models\ParticipantLetter;
use App\Models\QrCode;
use App\Models\QrExclusiveClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\GameHelpers;
use Tests\TestCase;

/**
 * Concurrence RÉELLE : plusieurs processus PHP indépendants (connexions MySQL
 * distinctes) exécutent la même action au même instant.
 *
 * Ces tests n'utilisent pas RefreshDatabase : les processus enfants doivent voir
 * des données réellement validées (COMMIT). La base de test est vidée avant et après.
 * Ils nécessitent MySQL (verrous InnoDB) : ils sont ignorés sur un autre SGBD.
 */
class ConcurrencyTest extends TestCase
{
    use GameHelpers;

    private const WORKERS = 8;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Tests de concurrence réservés à MySQL.');
        }

        $this->artisan('migrate');
        $this->truncateAll();
    }

    protected function tearDown(): void
    {
        $this->truncateAll();
        parent::tearDown();
    }

    public function test_scans_simultanes_du_meme_qr_par_le_meme_participant(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participant = Participant::factory()->for($campaign)->create();

        $results = $this->race(array_fill(0, self::WORKERS, ['scan', $participant->id, $qrs[0]->scanUrl()]));

        $this->assertSame(1, $this->tally($results, 'valid'), 'Une seule validation : '.implode(',', $results));
        $this->assertSame(1, ParticipantLetter::query()->where('participant_id', $participant->id)->count());
        $this->assertSame(1, $qrs[0]->fresh()->valid_scans_count);
        $this->assertSame(1, $participant->fresh()->letters_count);
    }

    public function test_scans_simultanes_de_deux_qr_de_la_meme_position(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(perLetter: 2);
        $participant = Participant::factory()->for($campaign)->create();

        // Alternance des deux QR de la position 1
        $jobs = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $jobs[] = ['scan', $participant->id, $qrs[$i % 2]->scanUrl()];
        }

        $results = $this->race($jobs);

        $this->assertSame(1, $this->tally($results, 'valid'), implode(',', $results));
        $this->assertSame(1, ParticipantLetter::query()->where('participant_id', $participant->id)->count());
    }

    public function test_premier_arrive_sous_concurrence(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['validation_mode' => 'first_come']);
        $participants = Participant::factory()->count(self::WORKERS)->for($campaign)->create();

        $results = $this->race($participants->map(fn ($p) => ['scan', $p->id, $qrs[0]->scanUrl()])->all());

        $this->assertSame(1, $this->tally($results, 'valid'), implode(',', $results));
        $this->assertSame(self::WORKERS - 1, $this->tally($results, 'already_claimed'));
        $this->assertSame(1, QrExclusiveClaim::query()->count());
        $this->assertSame(1, ParticipantLetter::query()->count());
    }

    public function test_multi_participants_sous_concurrence(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes();
        $participants = Participant::factory()->count(self::WORKERS)->for($campaign)->create();

        $results = $this->race($participants->map(fn ($p) => ['scan', $p->id, $qrs[0]->scanUrl()])->all());

        $this->assertSame(self::WORKERS, $this->tally($results, 'valid'), implode(',', $results));
        $this->assertSame(self::WORKERS, $qrs[0]->fresh()->valid_scans_count, 'Le compteur ne doit perdre aucun incrément');
    }

    public function test_propositions_simultanees_ne_depassent_pas_le_quota(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['max_word_attempts' => 3, 'require_word_entry' => true]);
        $participant = Participant::factory()->for($campaign)->create();
        $this->collectAll($participant, $qrs);

        $results = $this->race(array_fill(0, self::WORKERS, ['word', $participant->id, 'MAUVAIS']));

        $this->assertSame(3, MysteryAttempt::query()->where('participant_id', $participant->id)->count(), implode(',', $results));
        $this->assertSame(3, $participant->fresh()->word_attempts);
        $this->assertSame(3, $this->tally($results, 'incorrect'));
    }

    public function test_bonne_reponse_simultanee_une_seule_reussite(): void
    {
        [$campaign, $qrs] = $this->campaignWithQrCodes(['require_word_entry' => true]);
        $participant = Participant::factory()->for($campaign)->create();
        $this->collectAll($participant, $qrs);

        $results = $this->race(array_fill(0, self::WORKERS, ['word', $participant->id, 'ARTISAN']));

        $this->assertSame(1, $this->tally($results, 'correct'), implode(',', $results));
        $this->assertSame(1, MysteryAttempt::query()->where('is_correct', true)->count());
    }

    /**
     * Lance tous les processus, les synchronise sur un instant commun et renvoie leurs sorties.
     *
     * @param  list<array{0: string, 1: int, 2: string}>  $jobs
     * @return list<string>
     */
    private function race(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 4); // temps de démarrage des processus sous Windows
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SMS_DRIVER' => 'null',
        ];

        $processes = array_map(function (array $job) use ($startAt, $env) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/concurrent_worker.php'), $job[0], (string) $job[1], $job[2], $startAt],
                base_path(),
                $env,
                timeout: 60,
            );
            $process->start();

            return $process;
        }, $jobs);

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Processus en échec : '.$process->getErrorOutput().$process->getOutput());
            $this->assertStringContainsString('ready-in-time', $process->getErrorOutput(),
                'Un processus a démarré après l\'instant commun : la course n\'était pas simultanée (augmenter le délai).');

            return trim($process->getOutput());
        }, $processes);
    }

    private function tally(array $results, string $value): int
    {
        return count(array_filter($results, fn ($r) => $r === $value));
    }

    private function truncateAll(): void
    {
        Schema::disableForeignKeyConstraints();

        // Uniquement les tables de la base de test (Laravel 12 liste sinon tous les schémas du serveur)
        foreach (Schema::getTableListing(schema: DB::getDatabaseName(), schemaQualified: false) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
