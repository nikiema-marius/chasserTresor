<div>
    <div class="flex items-center gap-3">
        <x-avatar :participant="$participant" size="size-12 text-lg" />
        <div>
            <p class="text-lg font-black">Re-bonjour {{ $participant->first_name }} 👋</p>
            <p class="text-sm text-gray-600">Un nouveau jeu a commencé !</p>
        </div>
    </div>

    @if ($campaign)
        <section class="card mt-5 border-2 border-om">
            <p class="text-xs font-bold tracking-wide text-om-dark uppercase">Nouveau jeu</p>
            <h1 class="mt-1 text-2xl font-black">{{ $campaign->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                Du {{ $campaign->starts_at->setTimezone(config('game.display_timezone'))->translatedFormat('j F') }}
                au {{ $campaign->ends_at->setTimezone(config('game.display_timezone'))->translatedFormat('j F Y') }}
                · {{ $campaign->word_length }} lettres à trouver
            </p>
            <p class="mt-3 text-sm">Ton compte est conservé : pas besoin de te réinscrire. Ta collection de lettres repart à zéro pour ce nouveau jeu.</p>
        </section>

        @if ($campaign->rules_md)
            <section class="card mt-4">
                <h2 class="mb-2 font-bold">Les règles</h2>
                <div class="prose-rules">{!! $campaign->rulesHtml() !!}</div>
            </section>
        @endif

        <form wire:submit="join" class="mt-5 grid gap-3">
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="consent_rules" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>J'accepte le <a href="{{ route('regulation') }}" target="_blank" class="font-semibold text-om-dark underline">règlement</a> de ce jeu (version {{ $campaign->regulation_version }}).</span>
            </label>
            @error('consent_rules') <p class="error -mt-2">{{ $message }}</p> @enderror

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="age_confirmed" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>J'ai au moins {{ $campaign->min_age }} ans.</span>
            </label>
            @error('age_confirmed') <p class="error -mt-2">{{ $message }}</p> @enderror

            <button type="submit" class="btn btn-primary mt-2 w-full text-lg" wire:loading.attr="disabled">Participer <span aria-hidden="true">→</span></button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm underline">Ce n'est pas moi — me déconnecter</button>
        </form>
    @endif
</div>
