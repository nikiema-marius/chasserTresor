<div>
    {{-- Retour d'un scan fait avec l'appareil photo natif : même écran que le scanner --}}
    @if ($flash)
        <div x-data="{ open: true, left: {{ (int) ($flash['retry_after'] ?? 0) }} }" x-show="open"
             x-init="if (left > 0) { const t = setInterval(() => { if (--left <= 0) clearInterval(t) }, 1000) }"
             class="fixed inset-0 z-40 overflow-y-auto bg-white text-ink">
            <x-scan-reveal :outcome="$flash" :count="$participant->letters_count" :total="$campaign->word_length">
                <x-slot:actions>
                    @if ($flash['completed'])
                        <a href="{{ route('mystery') }}" class="btn btn-primary w-full text-lg">{{ ! empty($flash['solved']) ? 'Voir mon gain 🎁' : 'Découvrir le mot mystère' }}</a>
                    @else
                        <a href="{{ route('scanner') }}" class="btn btn-primary w-full text-lg" x-bind:class="left > 0 && 'pointer-events-none opacity-50'"
                           x-text="left > 0 ? 'Prochain scan dans ' + left + ' s' : 'Continuer la chasse'">Continuer la chasse</a>
                    @endif
                    <button type="button" class="btn btn-ghost w-full" x-on:click="open = false">Retour au tableau de bord</button>
                </x-slot:actions>
            </x-scan-reveal>
        </div>
    @endif

    <div class="flex items-center gap-3">
        <x-avatar :participant="$participant" size="size-12 text-lg" />
        <div>
            <p class="text-lg font-black">Bonjour {{ $participant->pseudo }} 👋</p>
            <p class="text-sm text-gray-600">Bienvenue sur la chasse au trésor du SIAO !</p>
        </div>
    </div>

    <p class="mt-4 flex items-center justify-between gap-2 rounded-xl bg-white px-4 py-2 text-sm shadow-sm ring-1 ring-black/5">
        <span class="flex items-center gap-2"><span class="size-3 shrink-0 rounded-full" style="background: {{ $campaign->color }}" aria-hidden="true"></span>Jeu : <strong>{{ $campaign->name }}</strong></span>
        @if ($otherGames->isNotEmpty())
            <a href="#mes-jeux" class="font-semibold text-om-dark underline">Changer de jeu</a>
        @endif
    </p>

    <section class="card mt-3">
        <h2 class="font-bold">Ma collection de lettres</h2>
        <div class="mt-2 flex items-baseline justify-between">
            <p class="text-2xl font-black tabular-nums">{{ $participant->letters_count }} / {{ $campaign->word_length }}</p>
            <p class="font-bold tabular-nums">{{ $participant->progressPercent() }}%</p>
        </div>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-valuenow="{{ $participant->progressPercent() }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-om transition-all duration-700" style="width: {{ $participant->progressPercent() }}%"></div>
        </div>

        <x-letter-tiles :groups="$tileGroups" class="mt-4" />

        @if ($campaign->letters_display === \App\Enums\LettersDisplay::Shuffled && ! $participant->hasSolved())
            <p class="mt-3 text-center text-xs text-gray-500">
                Les lettres sont dans l'ordre où tu les as trouvées : à toi de reconstituer
                {{ $wordCount > 1 ? "l'expression ({$wordCount} mots)" : 'le mot' }} !
            </p>
        @endif
    </section>

    @if (! $participant->isActive())
        <div class="card mt-4 border-l-4 border-red-700 font-semibold">{{ \App\Enums\ScanResult::ParticipantBlocked->message() }}</div>
    @elseif ($availability !== null && ! $participant->hasSolved())
        <div class="card mt-4 border-l-4 border-om font-semibold">{{ $availability->message() }}</div>
    @elseif (! $participant->hasCompleted())
        <a href="{{ route('scanner') }}" class="btn btn-primary mt-5 w-full py-4 text-lg">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg>
            Scanner un QR code
        </a>
    @endif

    {{-- Gagnant : accès direct à l'écran à présenter au chapiteau --}}
    @if ($participant->hasCompleted() && ($participant->hasSolved() || ! $campaign->require_word_entry))
        <a href="{{ route('mystery') }}" class="card mt-4 block border-4 border-ink bg-om text-center">
            <p class="text-3xl font-black">🏆 BRAVOOO !</p>
            <p class="mt-1 font-bold">
                {{ $participant->hasClaimedPrize() ? 'Ton gain a été remis. Merci d\'avoir joué !' : 'Va récupérer ton gain au chapiteau Orange Money.' }}
            </p>
            <span class="btn btn-dark mt-3">{{ $participant->hasClaimedPrize() ? 'Voir le mot mystère' : 'Afficher mon justificatif 🎁' }}</span>
        </a>
    @endif

    {{-- Le mot mystère : verrouillé tant que toutes les lettres ne sont pas collectées --}}
    @php
        $mysteryOpen = $participant->hasCompleted() && ($participant->hasSolved() || $participant->remainingAttempts() > 0);
    @endphp
    @if ($campaign->require_word_entry || ! $participant->hasCompleted())
    <a @if ($mysteryOpen) href="{{ route('mystery') }}" @endif
       @class(['card mt-4 flex items-center justify-between gap-3', 'border-2 border-om' => $mysteryOpen && ! $participant->hasSolved(), 'opacity-80' => ! $mysteryOpen])>
        <div>
            <p class="font-bold">Le mot mystère</p>
            <p class="text-sm text-gray-600">
                @if ($participant->hasSolved())
                    Trouvé ! Tu es le {{ $participant->solvedRank() }}{{ $participant->solvedRank() === 1 ? 'er' : 'e' }} à l'avoir découvert.
                @elseif ($participant->hasCompleted() && $participant->remainingAttempts() === 0)
                    Tu as utilisé tous tes essais. Merci d'avoir participé !
                @elseif ($participant->hasCompleted())
                    Tu as toutes les lettres : découvre le mot !
                @else
                    Collecte toutes les lettres pour le découvrir !
                @endif
            </p>
        </div>
        <span @class(['grid size-10 shrink-0 place-items-center rounded-full', 'bg-om text-ink' => $mysteryOpen, 'bg-gray-100 text-gray-500' => ! $mysteryOpen])>
            @if ($participant->hasSolved())
                🏆
            @elseif ($mysteryOpen)
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
            @else
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            @endif
        </span>
    </a>
    @endif

    @if ($otherGames->isNotEmpty())
        <section id="mes-jeux" class="mt-8">
            <h2 class="text-lg font-black">{{ $otherGames->count() > 1 ? 'Mes autres jeux' : 'Un autre jeu en ligne' }}</h2>
            <p class="text-sm text-gray-600">Même compte, une collection de lettres par jeu.</p>
            <x-games-list :games="$otherGames" class="mt-3" />
        </section>
    @endif

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('regulation', $campaign) }}" class="font-semibold underline">★ Voir les règles du jeu</a>
    </p>
</div>
