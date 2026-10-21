<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Journalisation des événements sensibles dans la table audit_logs.
 */
class AuditLogger
{
    public function log(string $event, ?Model $subject = null, array $properties = [], ?Model $actor = null): AuditLog
    {
        $actor ??= Auth::guard('admin')->user() ?? Auth::guard('participant')->user();

        $actorType = match (true) {
            $actor instanceof Admin => ActorType::Admin,
            $actor instanceof Participant => ActorType::Participant,
            default => ActorType::System,
        };

        $request = app()->runningInConsole() ? null : request();

        $entry = AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);

        // Copie dans un fichier dédié : survit à une altération de la base
        Log::channel('security')->info($event, [
            'actor' => $actorType->value.':'.($actor?->getKey() ?? '-'),
            'subject' => $entry->subject_type ? $entry->subject_type.':'.$entry->subject_id : null,
            'ip' => $entry->ip,
            'properties' => $properties,
        ]);

        return $entry;
    }
}
