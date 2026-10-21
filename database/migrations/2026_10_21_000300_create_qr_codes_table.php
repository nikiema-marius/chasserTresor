<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            // Emplacement (position) du mot : jamais exposé dans l'URL du QR
            $table->foreignId('campaign_letter_id')->constrained()->restrictOnDelete();

            // SHA-256 du jeton : seule valeur utilisée pour la recherche
            $table->char('token_hash', 64)->unique();
            // Jeton chiffré : uniquement pour régénérer l'image du QR
            $table->text('token_encrypted');
            // Code court de saisie manuelle (secours caméra refusée)
            $table->char('manual_code_hash', 64)->nullable()->unique();
            $table->text('manual_code_encrypted')->nullable();

            // Emplacement physique (« Pavillon A – Stand 12 »)
            $table->string('label', 120);
            $table->enum('status', ['active', 'disabled', 'revoked'])->default('active');
            // NULL = hérite du mode de la campagne
            $table->enum('validation_mode', ['per_participant', 'first_come'])->nullable();

            // Compteur dénormalisé (statistiques)
            $table->unsignedInteger('valid_scans_count')->default(0);

            $table->dateTime('disabled_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
