<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une ligne par POSITION du mot mystère (gère les lettres répétées :
     * « ARTISAN » → A@1 et A@6 sont deux emplacements distincts).
     */
    public function up(): void
    {
        Schema::create('campaign_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position'); // 1..n
            $table->char('letter', 1);               // A-Z normalisée
            $table->timestamps();

            $table->unique(['campaign_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_letters');
    }
};
