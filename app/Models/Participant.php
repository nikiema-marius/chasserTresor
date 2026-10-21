<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Participant au jeu (garde « participant »).
 * Authentification par téléphone + OTP SMS, ou par PIN si l'OTP est désactivé.
 */
class Participant extends Authenticatable
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    // Millisecondes : nécessaires pour départager les ex-aequo au classement
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'campaign_id',
        'first_name',
        'last_name',
        'phone_e164',
        'consent_rules_at',
        'consent_rules_version',
        'consent_marketing',
        'age_confirmed',
        'device_id',
        'registration_ip',
        'user_agent',
    ];

    protected $hidden = [
        'pin_hash',
        'remember_token',
        'phone_e164',
        'registration_ip',
        'user_agent',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'phone_verified_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'consent_rules_at' => 'datetime',
            'consent_marketing' => 'boolean',
            'age_confirmed' => 'boolean',
            'letters_count' => 'integer',
            'last_valid_scan_at' => 'datetime',
            'completed_at' => 'datetime',
            'word_attempts' => 'integer',
            'bonus_word_attempts' => 'integer',
            'solved_at' => 'datetime',
            'prize_claimed_at' => 'datetime',
            'suspicion_score' => 'integer',
            'suspicion_flags' => 'array',
            'anonymized_at' => 'datetime',
        ];
    }

    /** Le « mot de passe » du participant est son PIN haché (mode sans OTP). */
    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function participantLetters(): HasMany
    {
        return $this->hasMany(ParticipantLetter::class);
    }

    /** Positions du mot déjà collectées. */
    public function collectedLetters(): BelongsToMany
    {
        return $this->belongsToMany(CampaignLetter::class, 'participant_letters')
            ->withPivot(['qr_code_id', 'collected_at']);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function mysteryAttempts(): HasMany
    {
        return $this->hasMany(MysteryAttempt::class);
    }

    public function rewardWinners(): HasMany
    {
        return $this->hasMany(RewardWinner::class);
    }

    // ------------------------------------------------------------------
    // État de la partie
    // ------------------------------------------------------------------

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /** Numéro masqué pour l'affichage : +226 70 •• •• 56 */
    protected function maskedPhone(): Attribute
    {
        return Attribute::get(function () {
            $phone = (string) $this->phone_e164;

            return strlen($phone) > 6
                ? substr($phone, 0, 6).str_repeat('•', strlen($phone) - 8).substr($phone, -2)
                : $phone;
        });
    }

    public function isActive(): bool
    {
        return $this->status === ParticipantStatus::Active;
    }

    public function hasCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function hasSolved(): bool
    {
        return $this->solved_at !== null;
    }

    public function allowedAttempts(): int
    {
        return $this->campaign->max_word_attempts + $this->bonus_word_attempts;
    }

    public function remainingAttempts(): int
    {
        return max(0, $this->allowedAttempts() - $this->word_attempts);
    }

    public function hasClaimedPrize(): bool
    {
        return $this->prize_claimed_at !== null;
    }

    /**
     * Code de vérification du gain (6 caractères), présenté au chapiteau Orange Money.
     * Dérivé de APP_KEY : impossible à deviner ou à fabriquer pour un autre participant.
     */
    public function prizeCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $hash = hash_hmac('sha256', 'prize:'.$this->id, (string) config('app.key'), binary: true);

        return implode('', array_map(fn ($i) => $alphabet[ord($hash[$i]) & 31], range(0, 5)));
    }

    public static function findByPrizeCode(int $campaignId, string $code): ?self
    {
        $code = strtoupper(preg_replace('/[\s-]+/', '', $code) ?? '');

        return static::query()
            ->where('campaign_id', $campaignId)
            ->whereNotNull('solved_at')
            ->get()
            ->first(fn (self $p) => hash_equals($p->prizeCode(), $code));
    }

    /** Rang provisoire parmi ceux qui ont trouvé le mot (1 = premier), null si pas trouvé. */
    public function solvedRank(): ?int
    {
        if (! $this->solved_at) {
            return null;
        }

        return static::query()
            ->where('campaign_id', $this->campaign_id)
            ->whereNotNull('solved_at')
            ->where(fn ($q) => $q->where('solved_at', '<', $this->solved_at)
                ->orWhere(fn ($q) => $q->where('solved_at', $this->solved_at)->where('id', '<', $this->id)))
            ->count() + 1;
    }

    public function progressPercent(): int
    {
        $length = $this->campaign->word_length;

        return $length > 0 ? (int) floor($this->letters_count * 100 / $length) : 0;
    }
}
