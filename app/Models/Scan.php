<?php

namespace App\Models;

use App\Enums\ScanResult;
use App\Enums\ScanSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'une tentative de scan (valide ou non). Table en ajout seul.
 */
class Scan extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'campaign_id',
        'participant_id',
        'qr_code_id',
        'source',
        'result',
        'token_fingerprint',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'source' => ScanSource::class,
            'result' => ScanResult::class,
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }
}
