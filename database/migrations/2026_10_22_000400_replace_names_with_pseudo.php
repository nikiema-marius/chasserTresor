<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inscription simplifiée : pseudo + téléphone + code secret.
     * Le prénom existant devient le pseudo, le nom est supprimé (minimisation des données).
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->renameColumn('first_name', 'pseudo');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('last_name', 80)->default('')->after('pseudo');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->renameColumn('pseudo', 'first_name');
        });
    }
};
