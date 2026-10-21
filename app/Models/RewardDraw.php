<?php

namespace App\Models;

use App\Enums\RewardDrawStatus;
use App\Enums\RewardMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Attribution de récompenses : proposition → validation par un super-admin.
 */
class RewardDraw extends Model
{
    protected $fillable = [
        'campaign_id',
        'mode',
        'prize_label',
        'winners_count',
        'eligibility',
        'seed',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mode' => RewardMode::class,
            'status' => RewardDrawStatus::class,
            'eligibility' => 'array',
            'winners_count' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(RewardWinner::class)->orderBy('rank');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'validated_by');
    }
}
