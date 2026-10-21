<?php

namespace App\Models;

use App\Enums\QrStatus;
use App\Enums\ValidationMode;
use App\Services\QrTokenService;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'status',
        'validation_mode',
        'disabled_at',
        'revoked_at',
        'revoked_reason',
    ];

    // Les secrets ne doivent jamais être sérialisés
    protected $hidden = [
        'token_hash',
        'token_encrypted',
        'manual_code_hash',
        'manual_code_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'status' => QrStatus::class,
            'validation_mode' => ValidationMode::class,
            'token_encrypted' => 'encrypted',
            'manual_code_encrypted' => 'encrypted',
            'disabled_at' => 'datetime',
            'revoked_at' => 'datetime',
            'valid_scans_count' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Création sécurisée
    // ------------------------------------------------------------------

    /**
     * Crée un QR code pour une position du mot, avec jeton et code manuel aléatoires.
     */
    public static function createForLetter(CampaignLetter $letter, string $label, ?ValidationMode $mode = null): self
    {
        $tokens = app(QrTokenService::class);
        $token = $tokens->generateToken();
        $manual = $tokens->generateManualCode();

        $qr = new self(['label' => $label, 'validation_mode' => $mode, 'status' => QrStatus::Active]);
        $qr->forceFill([
            'campaign_id' => $letter->campaign_id,
            'campaign_letter_id' => $letter->getKey(),
            'token_hash' => $tokens->hashToken($token),
            'token_encrypted' => $token,
            'manual_code_hash' => $tokens->hashManualCode($manual),
            'manual_code_encrypted' => $manual,
        ])->save();

        return $qr;
    }

    /**
     * Génère $perLetter QR codes pour chaque position du mot de la campagne.
     * Retourne le nombre de QR créés.
     */
    public static function generateForCampaign(Campaign $campaign, int $perLetter, string $labelPrefix = 'À placer'): int
    {
        $created = 0;

        DB::transaction(function () use ($campaign, $perLetter, $labelPrefix, &$created) {
            foreach ($campaign->letters()->get() as $letter) {
                $existing = $letter->qrCodes()->count();

                for ($i = 1; $i <= $perLetter; $i++) {
                    self::createForLetter($letter, "{$labelPrefix} {$letter->position}-".($existing + $i));
                    $created++;
                }
            }
        });

        return $created;
    }

    public static function findByToken(string $token): ?self
    {
        $tokens = app(QrTokenService::class);

        if (! $tokens->isWellFormedToken($token)) {
            return null;
        }

        return static::query()->where('token_hash', $tokens->hashToken($token))->first();
    }

    public static function findByManualCode(string $input): ?self
    {
        $tokens = app(QrTokenService::class);
        $code = $tokens->normalizeManualCode($input);

        if ($code === null) {
            return null;
        }

        return static::query()->where('manual_code_hash', $tokens->hashManualCode($code))->first();
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignLetter(): BelongsTo
    {
        return $this->belongsTo(CampaignLetter::class);
    }

    public function exclusiveClaim(): HasOne
    {
        return $this->hasOne(QrExclusiveClaim::class);
    }

    public function participantLetters(): HasMany
    {
        return $this->hasMany(ParticipantLetter::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    // ------------------------------------------------------------------
    // Accesseurs métier
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', QrStatus::Active);
    }

    public function plainToken(): string
    {
        return $this->token_encrypted;
    }

    public function scanUrl(): string
    {
        return app(QrTokenService::class)->scanUrl($this->plainToken());
    }

    public function formattedManualCode(): ?string
    {
        return $this->manual_code_encrypted
            ? app(QrTokenService::class)->formatManualCode($this->manual_code_encrypted)
            : null;
    }

    public function effectiveValidationMode(): ValidationMode
    {
        return $this->validation_mode ?? $this->campaign->validation_mode;
    }
}
