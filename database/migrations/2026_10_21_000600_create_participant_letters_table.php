<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lettres collectées. Les contraintes uniques sont le filet de sécurité
     * ultime contre les doubles validations, même sous forte concurrence.
     */
    public function up(): void
    {
        Schema::create('participant_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_letter_id')->constrained()->restrictOnDelete();
            $table->foreignId('qr_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('collected_at', precision: 3);

            // Impossible d'obtenir deux fois la même position du mot
            $table->unique(['participant_id', 'campaign_letter_id']);
            // Impossible de valider deux fois le même QR
            $table->unique(['participant_id', 'qr_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_letters');
    }
};
