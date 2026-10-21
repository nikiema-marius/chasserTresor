<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-black">Journal de sécurité</h1>
        <select wire:model.live="event" class="field field-sm w-auto" aria-label="Filtrer par événement">
            <option value="">Tous les événements</option>
            @foreach ($events as $e)
                <option value="{{ $e }}">{{ $e }}</option>
            @endforeach
        </select>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Date</th><th>Événement</th><th>Acteur</th><th>Objet</th><th>Détails</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $log->created_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i:s') }}</td>
                        <td><span class="badge bg-gray-100 font-mono">{{ $log->event }}</span></td>
                        <td class="text-xs">
                            {{ $log->actor_type->value }}
                            @if ($log->actor_type === \App\Enums\ActorType::Admin) {{ $admins[$log->actor_id] ?? '#'.$log->actor_id }}
                            @elseif ($log->actor_id) #{{ $log->actor_id }} @endif
                        </td>
                        <td class="text-xs">{{ $log->subject_type ? $log->subject_type.' #'.$log->subject_id : '—' }}</td>
                        <td class="max-w-md font-mono text-[11px] break-all text-gray-600">{{ $log->properties ? json_encode($log->properties, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        <td class="font-mono text-xs">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">Aucun événement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
