<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->enum('status', ['draft', 'active', 'paused', 'closed'])->default('draft');

            // 1 = campagne publique courante, NULL sinon.
            // L'index unique (NULL autorisés plusieurs fois) garantit en base
            // qu'il n'existe jamais deux campagnes courantes.
            $table->unsignedTinyInteger('is_current')->nullable()->unique();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Horaires quotidiens d'ouverture du salon (NULL = pas de restriction)
            $table->time('daily_open_time')->nullable();
            $table->time('daily_close_time')->nullable();

            // Mot mystère chiffré (cast « encrypted », dépend de APP_KEY)
            $table->text('mystery_word');
            $table->unsignedTinyInteger('word_length');
            // Renseigné au premier scan valide : le mot ne peut plus être modifié
            $table->dateTime('word_locked_at')->nullable();

            $table->enum('validation_mode', ['per_participant', 'first_come'])->default('per_participant');
            $table->enum('letters_display', ['ordered', 'shuffled'])->default('shuffled');
            $table->unsignedTinyInteger('max_word_attempts')->default(5);
            // NULL = suit config('game.otp.enabled')
            $table->boolean('otp_required')->nullable();
            // Délai minimal entre deux scans valides d'un même participant (anti-triche)
            $table->unsignedSmallInteger('min_seconds_between_scans')->default(20);
            $table->unsignedTinyInteger('min_age')->default(18);

            $table->mediumText('rules_md')->nullable();
            $table->mediumText('regulation_md')->nullable();
            $table->string('regulation_version', 20)->default('1.0');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
