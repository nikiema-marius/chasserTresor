<x-layouts.app title="Mon profil">
    <h1 class="text-2xl font-black">Mon profil</h1>

    <div class="mt-4 flex items-center gap-4">
        <x-avatar :participant="$participant" size="size-16 text-2xl" />
        <div>
            <p class="text-lg font-black">{{ $participant->full_name }}</p>
            <p class="text-sm text-gray-600">{{ \App\Support\Phone::display($participant->phone_e164) }}</p>
        </div>
    </div>

    @php
        $chevron = '<svg class="size-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
        $items = [
            [route('letters'), 'Mes lettres', $participant->letters_count.' / '.$participant->campaign->word_length, 'M6 3h9l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM9 13h6M9 17h4'],
            [route('history'), 'Mon historique', null, 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2'],
            [route('regulation'), 'Règlement du jeu', null, 'M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM9 8h6M9 12h6M9 16h4'],
            [route('faq'), 'FAQ', null, 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01'],
            [route('my-data'), 'Mes données personnelles', null, 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z'],
        ];
    @endphp

    <nav class="card mt-6 p-0" aria-label="Mon compte">
        <ul class="divide-y divide-gray-100">
            @foreach ($items as [$url, $label, $badge, $icon])
                <li>
                    <a href="{{ $url }}" class="flex items-center gap-4 px-5 py-4 hover:bg-om-soft">
                        <svg class="size-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        <span class="flex-1 font-medium">{{ $label }}</span>
                        @if ($badge) <span class="text-sm font-semibold tabular-nums">{{ $badge }}</span> @endif
                        {!! $chevron !!}
                    </a>
                </li>
            @endforeach
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-om-soft">
                        <svg class="size-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3M10 17l-5-5 5-5M5 12h11"/></svg>
                        <span class="flex-1 font-medium">Déconnexion</span>
                        {!! $chevron !!}
                    </button>
                </form>
            </li>
        </ul>
    </nav>
</x-layouts.app>
