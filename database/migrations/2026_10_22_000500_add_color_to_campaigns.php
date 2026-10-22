<?php

use App\Models\Campaign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Couleur de repérage d'un jeu : imprimée sur ses QR codes et affichée aux participants,
     * pour ne pas confondre les QR de jeux différents (ex. le « A » d'ORANGE et celui de SIAO).
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->char('color', 7)->default('#FF7900')->after('slug');
        });

        // Campagnes existantes : une couleur différente chacune
        foreach (DB::table('campaigns')->orderBy('id')->pluck('id')->values() as $i => $id) {
            DB::table('campaigns')->where('id', $id)->update(['color' => Campaign::PALETTE[$i % count(Campaign::PALETTE)]]);
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
