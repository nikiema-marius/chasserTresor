<div>
    <div class="text-center">
        <h1 class="text-2xl font-black">Inscription au jeu</h1>
        <p class="mt-1 text-sm text-gray-600">Pour participer à la chasse au trésor, crée ton compte en quelques secondes.</p>
    </div>

    @error('campaign')
        <div class="card mt-4 border-l-4 border-red-700 text-sm font-medium">{{ $message }}</div>
    @enderror

    @if (! $campaign && $choices->isEmpty())
        <div class="card mt-6 text-center text-sm font-semibold">Aucun jeu n'est en ligne pour le moment. Reviens bientôt !</div>
    @endif

    @if (($campaign || $choices->isNotEmpty()) && $step === 'form')
        <form wire:submit="submit" class="mt-6 grid gap-3" novalidate>
            {{-- Jeu de l'inscription --}}
            @if ($choices->isNotEmpty())
                <fieldset>
                    <legend class="label">Choisis ton jeu</legend>
                    <div class="grid gap-2">
                        @foreach ($choices as $choice)
                            <label @class(['flex cursor-pointer items-center gap-3 rounded-xl border-2 bg-white px-4 py-3', 'border-om' => (int) $chosenCampaignId === $choice->id, 'border-gray-200' => (int) $chosenCampaignId !== $choice->id])>
                                <input type="radio" wire:model.live="chosenCampaignId" value="{{ $choice->id }}" class="size-5 accent-om">
                                <span class="flex-1">
                                    <span class="block font-bold">{{ $choice->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $choice->word_length }} lettres à trouver</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Tu pourras rejoindre les autres jeux ensuite avec le même compte.</p>
                    @error('chosenCampaignId') <p class="error">{{ $message }}</p> @enderror
                </fieldset>
            @elseif ($campaign)
                <p class="rounded-xl bg-om-soft px-4 py-2 text-center text-sm">Jeu : <strong>{{ $campaign->name }}</strong></p>
            @endif

            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/></svg>
                    <div class="flex-1">
                        <label for="pseudo">Pseudo</label>
                        <input id="pseudo" type="text" wire:model="pseudo" autocomplete="nickname" maxlength="30" placeholder="Ex. Aminata226" autocapitalize="off" spellcheck="false" required>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">C'est le nom affiché dans le jeu (2 à 30 caractères).</p>
                @error('pseudo') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
                    <div class="flex-1">
                        <label for="phone">Numéro de téléphone</label>
                        <input id="phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="+226 70 12 34 56" maxlength="25" required>
                    </div>
                </div>
                @error('phone')
                    <p class="error">{{ $message }}</p>
                    @if (str_contains($message, 'déjà un compte'))
                        <a href="{{ route('login') }}" class="mt-1 inline-block text-sm font-bold underline">Se connecter</a>
                    @endif
                @enderror
            </div>

            @unless ($otpRequired)
                <div x-data="{ show: false }">
                    <div class="field-group">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <div class="flex-1">
                            <label for="pin">Code secret (4 chiffres)</label>
                            <input id="pin" x-bind:type="show ? 'text' : 'password'" type="password" wire:model="pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="new-password" class="tracking-[.4em]" required>
                        </div>
                        <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-gray-500 hover:bg-gray-100"
                                x-on:click="show = ! show" x-bind:aria-label="show ? 'Masquer le code' : 'Afficher le code'" aria-label="Afficher le code">
                            <svg x-show="! show" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg x-show="show" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6"/></svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Il te servira à te reconnecter. Évite 1234, 0000… Ne le communique à personne.</p>
                    @error('pin') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless

            <label class="mt-1 flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="consent_rules" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>J'accepte le <a href="{{ $campaign ? route('regulation', $campaign) : route('regulation') }}" target="_blank" class="font-semibold text-om-dark underline">règlement</a> du jeu et j'ai au moins {{ $campaign?->min_age ?? 18 }} ans.</span>
            </label>
            @error('consent_rules') <p class="error -mt-2">{{ $message }}</p> @enderror

            <label class="flex items-start gap-3 text-sm text-gray-600">
                <input type="checkbox" wire:model="consent_marketing" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>(Facultatif) J'accepte de recevoir les offres d'Orange Money par SMS.</span>
            </label>

            @error('throttle') <p class="error">{{ $message }}</p> @enderror

            <button type="submit" class="btn btn-primary mt-2 w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">{{ $otpRequired ? 'Recevoir mon code' : "S'inscrire" }}</span>
                <span wire:loading wire:target="submit">Patiente…</span>
            </button>

            <p class="text-center text-sm">Déjà inscrit ? <a href="{{ route('login') }}" class="font-bold text-om-dark underline">Se connecter</a></p>
            <p class="flex items-center justify-center gap-1.5 text-xs text-gray-500">
                <svg class="size-4 text-om" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                Vos données sont protégées
            </p>
        </form>
    @endif

    @if ($step === 'otp')
        <form wire:submit="verifyOtp" class="mt-6 grid gap-4">
            <p class="text-center text-sm">Un code à {{ config('game.otp.length') }} chiffres a été envoyé par SMS au <strong>{{ \App\Support\Phone::display($pendingPhone) }}</strong>.</p>

            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>
                    <div class="flex-1">
                        <label for="code">Code de vérification SMS</label>
                        <input id="code" type="text" wire:model="code" class="tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ config('game.otp.length') }}" placeholder="Entre le code reçu" autofocus required>
                    </div>
                    <button type="button" wire:click="resendOtp" class="btn btn-ghost btn-sm shrink-0">Renvoyer</button>
                </div>
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full text-lg" wire:loading.attr="disabled">S'inscrire</button>
            <button type="button" wire:click="backToForm" class="text-sm underline">Modifier le numéro</button>
        </form>
    @endif
</div>
