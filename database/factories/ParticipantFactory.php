<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Campaign;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            // Numéro burkinabè fictif : +226 7X XX XX XX
            'phone_e164' => '+2267'.fake()->unique()->numerify('#######'),
            'consent_rules_at' => now(),
            'consent_rules_version' => '1.0',
            'consent_marketing' => false,
            'age_confirmed' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Participant $participant) {
            $participant->forceFill([
                'status' => ParticipantStatus::Active,
                'phone_verified_at' => now(),
            ]);
        });
    }

    public function blocked(): static
    {
        return $this->afterMaking(fn (Participant $p) => $p->forceFill(['status' => ParticipantStatus::Blocked]));
    }
}
