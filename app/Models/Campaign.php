<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\LettersDisplay;
use App\Enums\ScanResult;
use App\Enums\ValidationMode;
use App\Support\WordNormalizer;
use Carbon\CarbonInterface;
use Database\Factories\CampaignFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    /** Couleurs de repérage des jeux (bien distinctes, lisibles à l'impression). */
    public const PALETTE = ['#FF7900', '#0072CE', '#16A34A', '#7C3AED', '#DC2626', '#0D9488', '#CA8A04', '#DB2777'];

    // mystery_word et word_length ne sont modifiables que via applyMysteryWord()
    protected $fillable = [
        'name',
        'slug',
        'color',
        'status',
        'starts_at',
        'ends_at',
        'daily_open_time',
        'daily_close_time',
        'validation_mode',
        'letters_display',
        'max_word_attempts',
        'require_word_entry',
        'otp_required',
        'min_seconds_between_scans',
        'min_age',
        'rules_md',
        'regulation_md',
        'regulation_version',
    ];

    // Le mot mystère ne doit jamais fuiter dans une sérialisation (Livewire, JSON…)
    protected $hidden = ['mystery_word'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'validation_mode' => ValidationMode::class,
            'letters_display' => LettersDisplay::class,
            'mystery_word' => 'encrypted',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'word_locked_at' => 'datetime',
            'otp_required' => 'boolean',
            'require_word_entry' => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function letters(): HasMany
    {
        return $this->hasMany(CampaignLetter::class)->orderBy('position');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function mysteryAttempts(): HasMany
    {
        return $this->hasMany(MysteryAttempt::class);
    }

    public function rewardDraws(): HasMany
    {
        return $this->hasMany(RewardDraw::class);
    }

    // ------------------------------------------------------------------
    // Campagnes en ligne (plusieurs possibles en même temps)
    // ------------------------------------------------------------------

    /** En ligne : active et pas encore terminée (le début peut être à venir). */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Active)->where('ends_at', '>', now());
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function online(): \Illuminate\Support\Collection
    {
        return static::query()->online()->orderBy('starts_at')->orderBy('id')->get();
    }

    public function isOnline(): bool
    {
        return $this->status === CampaignStatus::Active && $this->ends_at->isFuture();
    }

    // ------------------------------------------------------------------
    // Couleur de repérage
    // ------------------------------------------------------------------

    /** Couleur proposée pour un nouveau jeu : la première de la palette non utilisée par un jeu en ligne. */
    public static function suggestColor(): string
    {
        $used = static::online()->pluck('color')->map(fn ($c) => strtoupper((string) $c))->all();

        return collect(self::PALETTE)->first(fn ($c) => ! in_array($c, $used, true)) ?? self::PALETTE[0];
    }

    /** Texte lisible sur la couleur du jeu : noir sur fond clair, blanc sur fond foncé. */
    public function textColor(): string
    {
        [$r, $g, $b] = sscanf(strtoupper($this->color ?: '#FF7900'), '#%02X%02X%02X') ?: [255, 121, 0];

        // Luminance relative (WCAG) : seuil choisi pour garder un contraste suffisant
        $lin = fn (int $v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return $luminance > 0.25 ? '#111111' : '#FFFFFF';
    }

    // ------------------------------------------------------------------
    // Règles métier
    // ------------------------------------------------------------------

    /**
     * Vérifie si le jeu est ouvert à l'instant donné.
     * Retourne null si ouvert, sinon le motif de fermeture.
     */
    public function availabilityAt(CarbonInterface $now): ?ScanResult
    {
        if ($this->status === CampaignStatus::Draft || $now->lt($this->starts_at)) {
            return ScanResult::CampaignNotStarted;
        }

        if (in_array($this->status, [CampaignStatus::Closed, CampaignStatus::Paused], true) || $now->gte($this->ends_at)) {
            return ScanResult::CampaignClosed;
        }

        if ($this->daily_open_time && $this->daily_close_time) {
            $localTime = $now->copy()->setTimezone(config('game.display_timezone'))->format('H:i:s');

            if ($localTime < $this->daily_open_time || $localTime >= $this->daily_close_time) {
                return ScanResult::OutsideHours;
            }
        }

        return null;
    }

    public function isOpenAt(CarbonInterface $now): bool
    {
        return $this->availabilityAt($now) === null;
    }

    /**
     * Mode de connexion (SMS ou PIN) : réglage global GAME_OTP_ENABLED.
     * Un même compte joue à plusieurs campagnes : il ne peut pas changer de règle selon le jeu.
     */
    public function otpRequired(): bool
    {
        return (bool) config('game.otp.enabled');
    }

    public function isWordLocked(): bool
    {
        return $this->word_locked_at !== null;
    }

    /**
     * Crée une campagne et son mot mystère (positions incluses) en une transaction.
     *
     * @throws DomainException
     */
    public static function createWithWord(array $attributes, string $word): self
    {
        return DB::transaction(function () use ($attributes, $word) {
            $campaign = new self($attributes);
            $campaign->forceFill(['mystery_word' => '', 'word_length' => 0])->save();
            $campaign->applyMysteryWord($word);

            return $campaign;
        });
    }

    /**
     * Enregistre le mot mystère et synchronise les positions (campaign_letters).
     * Interdit une fois le mot verrouillé (premier scan valide).
     *
     * @throws DomainException
     */
    public function applyMysteryWord(string $word): void
    {
        if ($this->isWordLocked()) {
            throw new DomainException('Le mot mystère est verrouillé : des lettres ont déjà été collectées.');
        }

        // Forme d'affichage conservée (« MAX IT ») ; seules les lettres deviennent des positions
        $display = WordNormalizer::display($word);
        $normalized = str_replace(' ', '', $display);
        $min = (int) config('game.word.min_length');
        $max = (int) config('game.word.max_length');

        if (strlen($normalized) < $min || strlen($normalized) > $max) {
            throw new DomainException("Le mot mystère doit contenir entre {$min} et {$max} lettres (A-Z).");
        }

        DB::transaction(function () use ($normalized, $display) {
            $this->forceFill([
                'mystery_word' => $display,
                'word_length' => strlen($normalized),
            ])->save();

            // Positions en trop (mot raccourci) : refusé si des QR y sont rattachés
            $obsolete = $this->letters()->where('position', '>', strlen($normalized));

            if ((clone $obsolete)->whereHas('qrCodes')->exists()) {
                throw new DomainException('Impossible de raccourcir le mot : des QR codes sont rattachés aux positions supprimées.');
            }

            $obsolete->delete();

            foreach (str_split($normalized) as $index => $letter) {
                CampaignLetter::query()->updateOrCreate(
                    ['campaign_id' => $this->getKey(), 'position' => $index + 1],
                    ['letter' => $letter],
                );
            }
        });

        $this->unsetRelation('letters');
    }

    /**
     * Conditions à remplir avant d'activer la campagne.
     *
     * @return list<string> problèmes bloquants (vide = activable)
     */
    public function activationProblems(): array
    {
        $problems = [];

        if ($this->ends_at->lte($this->starts_at)) {
            $problems[] = 'La date de fin doit être postérieure à la date de début.';
        }

        if ($this->ends_at->isPast()) {
            $problems[] = 'La date de fin est déjà passée.';
        }

        $missing = $this->letters()
            ->whereDoesntHave('qrCodes', fn (Builder $q) => $q->where('status', 'active'))
            ->pluck('position');

        if ($missing->isNotEmpty()) {
            $problems[] = 'Aucun QR code actif pour la/les position(s) : '.$missing->implode(', ').'.';
        }

        if (blank($this->regulation_md)) {
            $problems[] = 'Le règlement est vide.';
        }

        return $problems;
    }

    /** Règles rendues en HTML sûr (HTML brut supprimé, liens dangereux interdits). */
    public function rulesHtml(): string
    {
        return self::safeMarkdown($this->rules_md);
    }

    public function regulationHtml(): string
    {
        return self::safeMarkdown($this->regulation_md);
    }

    private static function safeMarkdown(?string $markdown): string
    {
        return Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);
    }

    /**
     * Longueur de chaque mot de l'expression (« MAX IT » → [3, 2]).
     *
     * @return list<int>
     */
    public function wordLengths(): array
    {
        $display = WordNormalizer::display((string) $this->mystery_word);

        return $display === '' ? [] : array_map('strlen', explode(' ', $display));
    }

    /** Vérifie une proposition de mot (insensible à la casse, aux espaces, aux accents). */
    public function checkMysteryWord(string $submitted): bool
    {
        return WordNormalizer::equals($this->mystery_word, $submitted);
    }
}
