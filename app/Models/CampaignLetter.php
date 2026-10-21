<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une position du mot mystère (ex. « A » en position 6 de « ARTISAN »).
 */
class CampaignLetter extends Model
{
    protected $fillable = ['campaign_id', 'position', 'letter'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function participantLetters(): HasMany
    {
        return $this->hasMany(ParticipantLetter::class);
    }
}
