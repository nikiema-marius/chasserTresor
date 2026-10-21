<?php

namespace Database\Factories;

use App\Enums\QrStatus;
use App\Models\Campaign;
use App\Models\CampaignLetter;
use App\Models\QrCode;
use App\Services\QrTokenService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Le jeton en clair reste accessible via $qr->plainToken().
 *
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    public function definition(): array
    {
        $tokens = app(QrTokenService::class);
        $token = $tokens->generateToken();
        $manual = $tokens->generateManualCode();

        return [
            'campaign_letter_id' => fn () => Campaign::factory()->create()->letters()->firstOrFail()->id,
            'campaign_id' => fn (array $attributes) => CampaignLetter::query()->findOrFail($attributes['campaign_letter_id'])->campaign_id,
            'label' => 'QR '.fake()->bothify('??-###'),
            'status' => QrStatus::Active,
            'token_hash' => $tokens->hashToken($token),
            'token_encrypted' => $token,
            'manual_code_hash' => $tokens->hashManualCode($manual),
            'manual_code_encrypted' => $manual,
        ];
    }

    public function forLetter(CampaignLetter $letter): static
    {
        return $this->state(fn () => [
            'campaign_letter_id' => $letter->id,
            'campaign_id' => $letter->campaign_id,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => QrStatus::Disabled, 'disabled_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => QrStatus::Revoked, 'revoked_at' => now()]);
    }
}
