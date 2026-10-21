<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proposition de mot mystère soumise par un participant.
 */
class MysteryAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'participant_id',
        'campaign_id',
        'attempt_number',
        'submitted',
        'is_correct',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'is_correct' => 'boolean',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
