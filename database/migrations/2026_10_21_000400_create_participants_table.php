<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            // Numéro normalisé E.164 (+22670123456)
            $table->string('phone_e164', 20);
            $table->dateTime('phone_verified_at', precision: 3)->nullable();

            // PIN de reconnexion (utilisé quand l'OTP SMS est désactivé)
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->dateTime('pin_locked_until')->nullable();

            // Consentements (RGPD) : règlement obligatoire, marketing facultatif
            $table->dateTime('consent_rules_at', precision: 3);
            $table->string('consent_rules_version', 20);
            $table->boolean('consent_marketing')->default(false);
            $table->boolean('age_confirmed')->default(false);

            $table->enum('status', ['active', 'blocked', 'disqualified'])->default('active');

            // Progression dénormalisée (mise à jour dans la transaction de scan)
            $table->unsignedTinyInteger('letters_count')->default(0);
            $table->dateTime('last_valid_scan_at', precision: 3)->nullable();
            $table->dateTime('completed_at', precision: 3)->nullable(); // toutes les lettres
            $table->unsignedTinyInteger('word_attempts')->default(0);
            // Essais supplémentaires accordés manuellement par un admin (action journalisée)
            $table->unsignedTinyInteger('bonus_word_attempts')->default(0);
            $table->dateTime('solved_at', precision: 3)->nullable();    // mot trouvé

            // Détection de participations suspectes (revue humaine)
            $table->unsignedSmallInteger('suspicion_score')->default(0);
            $table->json('suspicion_flags')->nullable();

            // Identifiant d'appareil (cookie signé) : indicatif, jamais bloquant seul
            $table->char('device_id', 36)->nullable();
            $table->string('registration_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->dateTime('anonymized_at')->nullable();
            $table->rememberToken();
            $table->timestamps(precision: 3);

            // Anti-inscriptions multiples : garanti par la base
            $table->unique(['campaign_id', 'phone_e164']);
            $table->index(['campaign_id', 'solved_at']);
            $table->index(['campaign_id', 'completed_at']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
