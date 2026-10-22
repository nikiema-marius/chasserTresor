<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\LettersDisplay;
use App\Enums\ValidationMode;
use App\Models\Campaign;
use App\Models\QrCode;
use Illuminate\Database\Seeder;

/**
 * Campagne de démonstration « ARTISAN » avec 2 QR codes par position.
 * Réservée aux environnements local / staging (jamais en production).
 */
class DemoCampaignSeeder extends Seeder
{
    public function run(): void
    {
        if (Campaign::query()->where('slug', 'siao-2026-demo')->exists()) {
            $this->command?->info('Campagne de démo déjà présente : inchangée.');

            return;
        }

        $campaign = Campaign::createWithWord([
            'name' => 'SIAO 2026 — Démo',
            'slug' => 'siao-2026-demo',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(14)->endOfDay(),
            'validation_mode' => ValidationMode::PerParticipant,
            'letters_display' => LettersDisplay::Shuffled,
            'max_word_attempts' => 5,
            'otp_required' => null, // suit GAME_OTP_ENABLED
            'min_seconds_between_scans' => 20,
            'min_age' => 18,
            'rules_md' => "1. Inscrivez-vous avec votre numéro de téléphone.\n"
                ."2. Trouvez et scannez les QR codes cachés sur le site du salon.\n"
                ."3. Chaque QR code vous révèle une lettre.\n"
                ."4. Reconstituez le mot mystère et tentez de gagner !",
            'regulation_md' => "## Règlement de démonstration\n\nÀ remplacer par le règlement officiel validé par le service juridique.",
            'regulation_version' => '1.0',
        ], 'ARTISAN');


        $perLetter = (int) config('game.qr.default_per_letter', 2);
        $rows = [];

        foreach ($campaign->letters as $letter) {
            for ($i = 1; $i <= $perLetter; $i++) {
                $qr = QrCode::createForLetter($letter, "Démo – position {$letter->position} – QR {$i}");
                $rows[] = [$qr->id, $letter->position, $letter->letter, $qr->formattedManualCode(), $qr->scanUrl()];
            }
        }

        $this->command?->info("Campagne de démo créée : {$campaign->name} (mot : ARTISAN)");
        $this->command?->table(['QR', 'Pos.', 'Lettre', 'Code manuel', 'URL de scan'], $rows);
    }
}
