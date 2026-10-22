<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Participants</h1>
            <p class="text-sm text-gray-500">{{ $campaign->name }} · {{ $participants->total() }} résultat(s)</p>
        </div>
    </div>

    <div class="card mt-4 grid gap-3 p-4 md:grid-cols-5">
        <input type="search" wire:model.live.debounce.400ms="search" class="field field-sm md:col-span-2" placeholder="Nom, prénom ou 4+ chiffres du téléphone" aria-label="Recherche">
        <select wire:model.live="progress" class="field field-sm" aria-label="Progression">
            <option value="">Toute progression</option>
            <option value="none">Aucune lettre</option>
            <option value="started">En cours</option>
            <option value="completed">Toutes les lettres</option>
            <option value="solved">Mot trouvé</option>
        </select>
        <select wire:model.live="status" class="field field-sm" aria-label="Statut">
            <option value="">Tous statuts</option>
            @foreach (\App\Enums\ParticipantStatus::cases() as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="sort" class="field field-sm" aria-label="Tri">
            <option value="recent">Plus récents</option>
            <option value="letters">Plus de lettres</option>
            <option value="solved">Ordre de réussite</option>
            <option value="suspicion">Score de suspicion</option>
        </select>
        <label class="flex items-center gap-2 text-sm md:col-span-5">
            <input type="checkbox" wire:model.live="suspects" class="size-4 accent-om"> Seulement les participants suspects (score ≥ {{ $threshold }})
        </label>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table">
            <thead>
                <tr><th>#</th><th>Participant</th><th>Téléphone</th><th>Inscrit</th><th>Progression</th><th>Mot trouvé</th><th>Essais</th><th>Statut</th><th>Suspicion</th></tr>
            </thead>
            <tbody>
                @forelse ($participants as $p)
                    <tr wire:key="p-{{ $p->id }}" @class(['bg-red-50' => $p->suspicion_score >= $threshold])>
                        <td>{{ $p->id }}</td>
                        <td><a href="{{ route('admin.participants.show', $p) }}" class="font-semibold hover:underline">{{ $p->pseudo }}</a></td>
                        <td class="font-mono text-xs">{{ $p->masked_phone }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $p->created_at->setTimezone(config('game.display_timezone'))->format('d/m H:i') }}</td>
                        <td class="min-w-36">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded bg-gray-200"><div class="h-full bg-om" style="width: {{ $p->progressPercent() }}%"></div></div>
                                <span class="text-xs tabular-nums">{{ $p->letters_count }}/{{ $campaign->word_length }}</span>
                            </div>
                        </td>
                        <td class="text-xs whitespace-nowrap">{{ $p->solved_at?->setTimezone(config('game.display_timezone'))->format('d/m H:i:s') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $p->word_attempts }}</td>
                        <td><span class="badge bg-gray-100">{{ $p->status->label() }}</span></td>
                        <td>
                            @if ($p->suspicion_score > 0)
                                <span @class(['badge', 'bg-red-100 text-red-900' => $p->suspicion_score >= $threshold, 'bg-yellow-100 text-yellow-900' => $p->suspicion_score < $threshold]) title="{{ implode(', ', array_keys($p->suspicion_flags ?? [])) }}">{{ $p->suspicion_score }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-6 text-center text-gray-500">Aucun participant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $participants->links() }}</div>
</div>
