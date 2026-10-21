<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE = [
        'valid', 'already_owned_qr', 'already_owned_slot', 'already_claimed',
        'invalid_token', 'disabled', 'revoked', 'campaign_not_started',
        'campaign_closed', 'outside_hours', 'too_fast', 'rate_limited',
        'participant_blocked',
    ];

    /**
     * Nouveau résultat de scan : QR authentique mais appartenant à une autre campagne.
     */
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->enum('result', [...self::BASE, 'other_campaign'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->enum('result', self::BASE)->change();
        });
    }
};
