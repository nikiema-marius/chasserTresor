<?php

use App\Models\Campaign;
use App\Models\OtpCode;
use App\Models\Participant;
use App\Services\Anonymizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches de maintenance (cron : * * * * * php artisan schedule:run)
|--------------------------------------------------------------------------
*/

// RGPD : anonymise les participants des campagnes terminées depuis plus de
// GAME_DATA_RETENTION_DAYS jours, et purge les codes OTP de plus de 24 h.
Artisan::command('game:purge {--dry-run : Affiche ce qui serait traité sans rien modifier}', function (Anonymizer $anonymizer) {
    $limit = now()->subDays((int) config('game.data_retention_days'));
    $campaigns = Campaign::withTrashed()->where('ends_at', '<', $limit)->pluck('id');

    $query = Participant::query()->whereIn('campaign_id', $campaigns)->whereNull('anonymized_at');
    $count = (clone $query)->count();
    $otp = OtpCode::query()->where('created_at', '<', now()->subDay());

    if ($this->option('dry-run')) {
        $this->info("{$count} participant(s) à anonymiser, {$otp->count()} code(s) OTP à purger.");

        return;
    }

    $query->lazyById(200)->each(fn (Participant $p) => $anonymizer->anonymize($p, 'durée de conservation dépassée'));
    $deleted = $otp->delete();

    $this->info("{$count} participant(s) anonymisé(s), {$deleted} code(s) OTP purgé(s).");
})->purpose('Anonymisation RGPD et purge des OTP expirés');

Schedule::command('game:purge')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
