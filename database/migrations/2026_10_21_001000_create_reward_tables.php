<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Récompenses : une « proposition » est générée puis doit être validée
     * explicitement par un super-admin. Aucune attribution automatique.
     */
    public function up(): void
    {
        Schema::create('reward_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['first_n', 'draw', 'ranking']);
            $table->string('prize_label', 150)->nullable();
            $table->unsignedSmallInteger('winners_count');
            // Critères d'admissibilité retenus (exclusion des suspects, dates…)
            $table->json('eligibility')->nullable();
            // Graine du tirage au sort : rend le tirage reproductible et vérifiable
            $table->char('seed', 64)->nullable();
            $table->enum('status', ['proposed', 'validated', 'cancelled'])->default('proposed');
            $table->foreignId('created_by')->constrained('admins')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('admins')->restrictOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reward_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_draw_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->enum('status', ['proposed', 'validated', 'rejected', 'notified', 'delivered'])->default('proposed');
            $table->string('rejection_reason', 255)->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['reward_draw_id', 'participant_id']);
            $table->unique(['reward_draw_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_winners');
        Schema::dropIfExists('reward_draws');
    }
};
