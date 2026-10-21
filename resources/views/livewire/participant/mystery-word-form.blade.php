<div>
    @if ($participant->hasSolved())
        {{-- Écran gagnant : à présenter au chapiteau Orange Money pour récupérer le gain --}}
        @php $tz = config('game.display_timezone'); @endphp
        <section class="relative overflow-hidden py-2 text-center">
            @unless ($participant->hasClaimedPrize())
                @foreach ([['8%', '6%', '#FF7900', '-20deg'], ['20%', '0%', '#16a34a', '35deg'], ['78%', '2%', '#FF7900', '15deg'], ['90%', '14%', '#111', '-40deg'], ['4%', '24%', '#16a34a', '60deg'], ['94%', '28%', '#FF7900', '-10deg']] as $i => [$left, $top, $color, $rotate])
                    <span class="confetti" style="left: {{ $left }}; top: {{ $top }}; background: {{ $color }}; rotate: {{ $rotate }}; animation-delay: {{ $i * 60 }}ms" aria-hidden="true"></span>
                @endforeach
            @endunless

            <div class="animate-letter-pop mx-auto grid size-24 place-items-center rounded-full bg-green-600 text-5xl" aria-hidden="true">🏆</div>
            <h1 class="mt-4 text-4xl font-black text-om">BRAVOOO !</h1>
            <p class="mt-1 text-lg font-bold">Tu as trouvé toutes les lettres !</p>

            <p class="mt-5 text-sm text-gray-600">Le mot mystère était :</p>
            <x-letter-tiles :groups="$tileGroups" class="mt-2" />

            @if ($participant->hasClaimedPrize())
                <div class="card mt-6 border-4 border-gray-400 bg-gray-50">
                    <p class="text-xl font-black text-gray-700">✓ GAIN DÉJÀ REMIS</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Le {{ $participant->prize_claimed_at->setTimezone($tz)->translatedFormat('j F à H\hi') }}. Merci d'avoir participé !
                    </p>
                </div>
            @else
                <div class="card mt-6 border-2 border-om bg-om-soft text-left">
                    <p class="text-lg font-black">🎁 Ton gain t'attend !</p>
                    <p class="mt-1 text-sm">
                        Rends-toi au <strong>chapiteau Orange Money</strong> et <strong>montre cet écran</strong> à l'équipe
                        pour récupérer ton gain, selon le règlement et dans la limite des lots disponibles.
                    </p>
                </div>

                {{-- Bloc de vérification pour l'équipe : l'horloge défile en direct (une capture d'écran se repère) --}}
                <div class="card mt-4 border-4 border-ink" x-data="{ now: '' }"
                     x-init="const tick = () => now = new Date().toLocaleTimeString('fr-FR'); tick(); setInterval(tick, 1000)">
                    <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">Justificatif gagnant</p>
                    <p class="mt-2 text-xl font-black">{{ $participant->full_name }}</p>
                    <p class="text-sm text-gray-600">{{ $participant->masked_phone }} · {{ $campaign->name }}</p>

                    <p class="mt-4 text-xs text-gray-500">Code de vérification</p>
                    <p class="font-mono text-4xl font-black tracking-[.3em] text-om-dark">{{ $prizeCode }}</p>

                    <div class="mt-4 flex items-center justify-between text-sm">
                        <span>Trouvé le {{ $participant->solved_at->setTimezone($tz)->translatedFormat('j F à H\hi') }} · {{ $rank }}{{ $rank === 1 ? 'er' : 'e' }}</span>
                        <span class="flex items-center gap-1.5 font-mono font-bold tabular-nums">
                            <span class="size-2.5 animate-pulse rounded-full bg-green-600" aria-hidden="true"></span>
                            <span x-text="now"></span>
                        </span>
                    </div>
                </div>
            @endif

            <a href="{{ route('dashboard') }}" class="btn btn-ghost mt-6 w-full">Retour à l'accueil</a>
        </section>
    @else
        {{-- Maquette 7 : saisie --}}
        <section>
            <h1 class="flex items-center gap-2 text-2xl font-black"><span aria-hidden="true">🏆</span> Le mot mystère</h1>
            <p class="mt-2 text-gray-700">Tu as collecté toutes les lettres ! Saisis le mot mystère pour valider ta participation.</p>

            <x-letter-tiles :groups="$tileGroups" small class="mt-5" />

            @if ($participant->remainingAttempts() > 0)
                <form wire:submit="submit" class="mt-6 grid gap-3" x-data>
                    <label for="word" class="sr-only">Ta proposition</label>
                    <div class="field-group">
                        <input id="word" type="text" wire:model="word" x-ref="word" class="text-lg font-bold tracking-widest uppercase"
                               maxlength="64" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Saisis le mot mystère" required>
                        <button type="button" class="grid size-6 shrink-0 place-items-center rounded-full bg-gray-300 text-xs text-white"
                                x-on:click="$wire.set('word', ''); $refs.word.focus()" aria-label="Effacer">✕</button>
                    </div>
                    @error('word') <p class="error">{{ $message }}</p> @enderror

                    <button type="submit" class="btn btn-primary w-full text-lg" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">Valider</span>
                        <span wire:loading wire:target="submit">Vérification…</span>
                    </button>

                    <p class="text-sm"><span class="font-semibold text-om-dark">Le mot doit contenir {{ $campaign->word_length }} lettres</span>{{ count($campaign->wordLengths()) > 1 ? ' (les espaces ne comptent pas)' : '' }}.</p>
                    <p class="text-sm text-gray-600">Il te reste <strong>{{ $participant->remainingAttempts() }} essai{{ $participant->remainingAttempts() > 1 ? 's' : '' }}</strong>.</p>
                </form>
            @endif

            @if ($feedback)
                <p @class(['mt-4 rounded-xl px-4 py-3 text-center font-bold', 'bg-green-100 text-green-900' => $success, 'animate-shake bg-red-50 text-red-800' => ! $success]) role="status">
                    {{ $feedback }}
                </p>
            @endif

            @if ($participant->remainingAttempts() === 0)
                <div class="card mt-4 text-center">
                    <p class="font-bold">Tu as utilisé tous tes essais.</p>
                    <p class="mt-1 text-sm text-gray-600">Merci d'avoir participé à la chasse au trésor du SIAO !</p>
                </div>
            @endif

            <a href="{{ route('dashboard') }}" class="btn btn-ghost mt-6 w-full">Retour au tableau de bord</a>
        </section>
    @endif
</div>
