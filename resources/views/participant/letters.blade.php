<x-layouts.app title="Mes lettres">
    <h1 class="text-2xl font-black">Mes lettres</h1>
    <p class="mt-1 text-sm text-gray-600">{{ $participant->letters_count }} / {{ $participant->campaign->word_length }} lettres collectées</p>

    <section class="card mt-4">
        <x-letter-tiles :groups="$tileGroups" />
    </section>

    <section class="card mt-4 p-0">
        <h2 class="px-5 pt-4 font-bold">Mes découvertes</h2>
        <ul class="mt-2 divide-y divide-gray-100">
            @forelse ($found as $i => $item)
                <li class="flex items-center gap-4 px-5 py-3">
                    <span class="tile tile-on size-10 text-xl">{{ $item->campaignLetter->letter }}</span>
                    <div class="text-sm">
                        <p class="font-semibold">Lettre n°{{ $i + 1 }}</p>
                        <p class="text-gray-500">{{ $item->collected_at->setTimezone(config('game.display_timezone'))->translatedFormat('l j F à H\hi') }}</p>
                    </div>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-gray-500">Aucune lettre pour l'instant. À toi de jouer !</li>
            @endforelse
        </ul>
    </section>

    @unless ($participant->hasCompleted())
        <a href="{{ route('scanner') }}" class="btn btn-primary mt-5 w-full text-lg">Scanner un QR code</a>
    @endunless
</x-layouts.app>
