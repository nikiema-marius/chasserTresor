<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\LettersDisplay;
use App\Enums\ValidationMode;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Campagne ouverte par défaut, avec le mot « ARTISAN » et ses 7 positions.
 *
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Campagne '.fake()->unique()->numerify('####');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => '#FF7900',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'validation_mode' => ValidationMode::PerParticipant,
            'letters_display' => LettersDisplay::Shuffled,
            'max_word_attempts' => 5,
            'otp_required' => false,
            'min_seconds_between_scans' => 0,
            'min_age' => 18,
            'regulation_version' => '1.0',
        ];
    }

    /** Mot mystère appliqué après création (génère les positions). */
    public function withWord(string $word): static
    {
        return $this->afterCreating(fn (Campaign $campaign) => $campaign->applyMysteryWord($word));
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Campaign $campaign) {
            // Valeurs provisoires : le vrai mot est posé par applyMysteryWord()
            $campaign->forceFill(['mystery_word' => 'ARTISAN', 'word_length' => 7]);
        })->afterCreating(function (Campaign $campaign) {
            if ($campaign->letters()->doesntExist()) {
                $campaign->applyMysteryWord($campaign->mystery_word);
            }
        });
    }


    public function firstCome(): static
    {
        return $this->state(fn () => ['validation_mode' => ValidationMode::FirstCome]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Closed,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(10),
        ]);
    }
}
