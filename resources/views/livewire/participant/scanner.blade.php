<div x-data="qrScanner" x-init="start()" x-on:scan-result.window="onResult($event.detail)" class="relative flex min-h-dvh flex-col bg-black text-white">
    {{-- Barre supérieure --}}
    <div class="flex items-center justify-between px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-3">
        <a href="{{ route('dashboard') }}" class="grid size-10 place-items-center rounded-full hover:bg-white/10" aria-label="Retour">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
        </a>
        <h1 class="text-lg font-bold">Scanner un QR code</h1>
        <button type="button" x-show="hasFlash" x-cloak x-on:click="toggleFlash()" class="grid size-10 place-items-center rounded-full hover:bg-white/10"
                :class="flashOn && 'text-om'" aria-label="Lampe torche">
            <svg class="size-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
        </button>
        <span x-show="! hasFlash" class="size-10" aria-hidden="true"></span>
    </div>

    {{-- Caméra + cadre de visée (ignorés par Livewire pour ne pas être recréés) --}}
    <div wire:ignore class="relative flex-1 overflow-hidden" x-show="['idle', 'starting', 'scanning', 'busy', 'result'].includes(state)">
        <video x-ref="video" class="absolute inset-0 size-full object-cover" playsinline muted></video>

        <div class="pointer-events-none absolute inset-0 grid place-items-center">
            <div class="relative aspect-square w-[72%] max-w-72 rounded-2xl shadow-[0_0_0_9999px_rgb(0_0_0/.55)]">
                <span class="absolute -top-1 -left-1 size-10 rounded-tl-2xl border-t-4 border-l-4 border-om"></span>
                <span class="absolute -top-1 -right-1 size-10 rounded-tr-2xl border-t-4 border-r-4 border-om"></span>
                <span class="absolute -bottom-1 -left-1 size-10 rounded-bl-2xl border-b-4 border-l-4 border-om"></span>
                <span class="absolute -right-1 -bottom-1 size-10 rounded-br-2xl border-r-4 border-b-4 border-om"></span>
                <span class="scan-line" x-show="state === 'scanning'"></span>
            </div>
        </div>

        <div x-show="state === 'starting' || state === 'idle'" class="absolute inset-0 grid place-items-center text-sm font-semibold">Ouverture de la caméra…</div>
        <div x-show="state === 'busy'" x-cloak class="absolute inset-0 grid place-items-center bg-black/60 text-lg font-bold">Vérification…</div>
    </div>

    {{-- Secours si la caméra est indisponible --}}
    <div x-show="['denied', 'nocamera', 'unsupported', 'insecure', 'error'].includes(state)" x-cloak class="flex flex-1 flex-col justify-center px-6">
        <div class="rounded-2xl bg-white/10 p-5">
            <p class="text-lg font-bold" x-text="{
                denied: 'Accès à la caméra refusé',
                nocamera: 'Aucune caméra détectée',
                unsupported: 'Ton navigateur ne permet pas de scanner',
                insecure: 'Connexion non sécurisée',
                error: 'La caméra n’a pas pu démarrer',
            }[state]"></p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-white/80">
                <li x-show="state === 'denied'">Autorise la caméra dans les réglages du navigateur (icône à gauche de l'adresse), puis réessaie.</li>
                <li>Ou ouvre l'<strong>appareil photo</strong> de ton téléphone et vise le QR code : le lien s'ouvrira directement.</li>
                <li>Ou saisis le code imprimé sous le QR code (ci-dessous).</li>
                <li>Dans WhatsApp ou Facebook, ouvre plutôt cette page dans Chrome ou Safari.</li>
            </ul>
            <button type="button" class="btn btn-primary mt-4 w-full" x-on:click="start()">Réessayer</button>
        </div>
    </div>

    {{-- Bas d'écran : consignes et saisie manuelle --}}
    <div class="px-6 pt-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-center" x-data="{ manual: false }">
        <p class="text-sm" x-show="state === 'scanning'">Aligne le QR code dans le cadre pour le scanner</p>
        <p class="mt-2 flex items-center justify-center gap-1.5 text-xs text-white/70">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
            Autorise l'accès à la caméra si nécessaire
        </p>

        <button type="button" class="mt-3 text-sm font-semibold underline" x-on:click="manual = ! manual">Saisir le code à la main</button>
        <form wire:submit="submitManual" class="mt-3 grid gap-2" x-show="manual || ['denied', 'nocamera', 'unsupported', 'insecure', 'error'].includes(state)" x-cloak>
            <label for="manualCode" class="sr-only">Code imprimé sous le QR code</label>
            <input id="manualCode" type="text" wire:model="manualCode" class="field text-center font-mono text-lg tracking-widest uppercase" placeholder="XXXXX-XXXXX" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false">
            @error('manualCode') <p class="text-sm font-semibold text-red-300">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">Valider le code</button>
        </form>
    </div>

    {{-- Résultat (calculé côté serveur) : maquettes 5 et 6 --}}
    @if ($outcome)
        {{-- left : secondes restantes avant le prochain scan autorisé (délai anti-triche) --}}
        <div class="fixed inset-0 z-40 overflow-y-auto bg-white text-ink" wire:key="outcome-{{ md5(json_encode($outcome).microtime()) }}"
             x-data="{ left: {{ (int) ($outcome['retry_after'] ?? 0) }} }"
             x-init="if (left > 0) { const t = setInterval(() => { if (--left <= 0) clearInterval(t) }, 1000) }">
            <x-scan-reveal :outcome="$outcome" :count="$participant->letters_count" :total="$participant->campaign->word_length">
                <x-slot:actions>
                    @if ($outcome['completed'])
                        <a href="{{ route('mystery') }}" class="btn btn-primary w-full text-lg">{{ ! empty($outcome['solved']) ? 'Voir mon gain 🎁' : 'Découvrir le mot mystère' }}</a>
                    @elseif ($outcome['success'])
                        <button type="button" class="btn btn-primary w-full text-lg" x-bind:disabled="left > 0"
                                x-on:click="$wire.dismiss().then(() => again())"
                                x-text="left > 0 ? 'Prochain scan dans ' + left + ' s' : 'Continuer la chasse'">Continuer la chasse</button>
                    @else
                        <button type="button" class="btn btn-primary w-full text-lg" x-bind:disabled="left > 0"
                                x-on:click="$wire.dismiss().then(() => again())"
                                x-text="left > 0 ? 'Réessayer dans ' + left + ' s' : 'Scanner un autre QR code'">Scanner un autre QR code</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-ghost w-full">Retour au tableau de bord</a>
                    @endif
                    @if ($outcome['retry_after'] && ! $outcome['completed'])
                        <p class="text-xs text-gray-500" x-show="left > 0">Un délai entre deux lettres évite la triche : patiente un instant.</p>
                    @endif
                </x-slot:actions>
            </x-scan-reveal>
        </div>
    @endif
</div>
