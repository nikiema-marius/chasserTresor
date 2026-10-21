<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mystery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            // Valeur normalisée (majuscules, sans espaces ni accents)
            $table->string('submitted', 64);
            $table->boolean('is_correct');
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at', precision: 3)->useCurrent();

            // Empêche de dépasser le quota de tentatives sous concurrence
            $table->unique(['participant_id', 'attempt_number']);
            $table->index(['campaign_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mystery_attempts');
    }
};
