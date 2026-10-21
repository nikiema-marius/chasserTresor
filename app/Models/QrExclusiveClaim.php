<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réclamation d'un QR en mode « premier arrivé » (clé primaire = qr_code_id).
 */
class QrExclusiveClaim extends Model
{
    protected $primaryKey = 'qr_code_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['qr_code_id', 'participant_id', 'claimed_at'];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
