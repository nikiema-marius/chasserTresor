<?php

namespace App\Models;

use App\Enums\WinnerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardWinner extends Model
{
    protected $fillable = [
        'reward_draw_id',
        'participant_id',
        'rank',
        'status',
        'rejection_reason',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WinnerStatus::class,
            'rank' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(RewardDraw::class, 'reward_draw_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
