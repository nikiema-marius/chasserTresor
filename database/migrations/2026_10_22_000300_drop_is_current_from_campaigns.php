<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plusieurs campagnes peuvent être en ligne en même temps (statut « active ») :
     * la notion de campagne « publique » unique disparaît.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['is_current']);
            $table->dropColumn('is_current');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_current')->nullable()->unique()->after('status');
        });
    }
};
