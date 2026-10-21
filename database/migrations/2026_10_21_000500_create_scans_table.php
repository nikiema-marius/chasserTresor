<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal de TOUTES les tentatives de scan (valides ou non).
     */
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('source', ['in_app', 'deep_link', 'manual_code']);
            $table->enum('result', [
                'valid', 'already_owned_qr', 'already_owned_slot', 'already_claimed',
                'invalid_token', 'disabled', 'revoked', 'campaign_not_started',
                'campaign_closed', 'outside_hours', 'too_fast', 'rate_limited',
                'participant_blocked',
            ]);
            // Début du SHA-256 d'un jeton inconnu : détecte le brute-force sans stocker le jeton
            $table->char('token_fingerprint', 16)->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at', precision: 3)->useCurrent();

            $table->index(['participant_id', 'created_at']);
            $table->index(['qr_code_id', 'result']);
            $table->index(['campaign_id', 'result', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
