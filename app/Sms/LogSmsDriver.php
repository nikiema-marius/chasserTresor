<?php

namespace App\Sms;

use App\Contracts\SmsDriver;
use Illuminate\Support\Facades\Log;

/**
 * Développement uniquement : le SMS est écrit dans storage/logs/laravel.log.
 * Refusé en production (voir AppServiceProvider).
 */
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): void
    {
        Log::info("[SMS → {$to}] {$message}");
    }
}
