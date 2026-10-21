<x-layouts.app title="Mon historique">
    <h1 class="text-2xl font-black">Mon historique</h1>
    <p class="mt-1 text-sm text-gray-600">Tous tes scans, réussis ou non.</p>

    <section class="card mt-4 p-0">
        <ul class="divide-y divide-gray-100">
            @forelse ($scans as $scan)
                <li class="flex items-center gap-4 px-5 py-3">
                    @if ($scan->result === $valid)
                        <span class="tile tile-on size-10 text-xl">{{ $scan->qrCode?->campaignLetter?->letter ?? '✓' }}</span>
                    @else
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-gray-100 text-xl font-black text-gray-500" aria-hidden="true">!</span>
                    @endif
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold">{{ $scan->result === $valid ? 'Lettre trouvée' : $scan->result->title() }}</p>
                        <p class="text-gray-500">{{ $scan->created_at->setTimezone(config('game.display_timezone'))->translatedFormat('j F à H\hi') }}</p>
                    </div>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-gray-500">Aucun scan pour l'instant.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
