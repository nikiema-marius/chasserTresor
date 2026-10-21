<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;

/**
 * Événement sensible journalisé. Table en ajout seul : aucune mise à jour.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'properties',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'properties' => 'array',
        ];
    }

    // Empêche toute modification d'une entrée existante
    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
