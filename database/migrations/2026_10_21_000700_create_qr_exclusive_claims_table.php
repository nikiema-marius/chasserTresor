<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode « premier arrivé » : la clé primaire sur qr_code_id garantit
     * qu'un seul participant peut réclamer le QR. En cas de scans simultanés,
     * MySQL rejette le second INSERT (doublon de clé 23000).
     */
    public function up(): void
    {
        Schema::create('qr_exclusive_claims', function (Blueprint $table) {
            $table->foreignId('qr_code_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->dateTime('claimed_at', precision: 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_exclusive_claims');
    }
};
