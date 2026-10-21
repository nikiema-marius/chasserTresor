<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - campaigns.require_word_entry : faut-il saisir le mot mystère pour gagner ?
     *   (false par défaut : toutes les lettres trouvées = gagné, le mot est révélé)
     * - participants.prize_claimed_* : remise du gain au chapiteau Orange Money
     *   (empêche de récupérer deux fois le même gain)
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('require_word_entry')->default(false)->after('max_word_attempts');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dateTime('prize_claimed_at', precision: 3)->nullable()->after('solved_at');
            $table->foreignId('prize_claimed_by')->nullable()->after('prize_claimed_at')->constrained('admins')->nullOnDelete();
        });

        // Parties déjà terminées : toutes les lettres trouvées = gagné (aucune campagne n'exige encore la saisie)
        DB::table('participants')
            ->whereNotNull('completed_at')
            ->whereNull('solved_at')
            ->update(['solved_at' => DB::raw('completed_at')]);
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prize_claimed_by');
            $table->dropColumn('prize_claimed_at');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('require_word_entry');
        });
    }
};
