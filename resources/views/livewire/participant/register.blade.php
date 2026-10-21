<div>
    <div class="text-center">
        <h1 class="text-2xl font-black">Inscription au jeu</h1>
        <p class="mt-1 text-sm text-gray-600">Pour participer à la chasse au trésor, crée ton compte en quelques secondes.</p>
    </div>

    @error('campaign')
        <div class="card mt-4 border-l-4 border-red-700 text-sm font-medium">{{ $message }}</div>
    @enderror

    @if ($campaign && $step === 'form')
        <form wire:submit="submit" class="mt-6 grid gap-3" novalidate>
            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/></svg>
                    <div class="flex-1">
                        <label for="first_name">Prénom</label>
                        <input id="first_name" type="text" wire:model="first_name" autocomplete="given-name" maxlength="80" placeholder="Aminata" required>
                    </div>
                </div>
                @error('first_name') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/></svg>
                    <div class="flex-1">
                        <label for="last_name">Nom</label>
                        <input id="last_name" type="text" wire:model="last_name" autocomplete="family-name" maxlength="80" placeholder="Diallo" required>
                    </div>
                </div>
                @error('last_name') <p class="error">{{ $message }}</p> @enderror
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
                <div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="field-group">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <div class="flex-1">
                                <label for="pin">Code PIN (4 chiffres)</label>
                                <input id="pin" type="password" wire:model="pin" inputmode="numeric" maxlength="4" autocomplete="new-password" class="tracking-[.4em]" required>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="flex-1">
                                <label for="pin_confirmation">Confirmation</label>
                                <input id="pin_confirmation" type="password" wire:model="pin_confirmation" inputmode="numeric" maxlength="4" autocomplete="new-password" class="tracking-[.4em]" required>
                            </div>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Il te servira à te reconnecter. Évite 1234, 0000… Ne le communique à personne.</p>
                    @error('pin') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless

            <label class="mt-1 flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="consent_rules" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>J'accepte le <a href="{{ route('regulation') }}" target="_blank" class="font-semibold text-om-dark underline">règlement</a> du jeu et les conditions de participation.</span>
            </label>
            @error('consent_rules') <p class="error -mt-2">{{ $message }}</p> @enderror

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="age_confirmed" class="mt-0.5 size-5 shrink-0 accent-om">
                <span>J'ai au moins {{ $campaign->min_age }} ans.</span>
            </label>
            @error('age_confirmed') <p class="error -mt-2">{{ $message }}</p> @enderror

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

    @if ($campaign && $step === 'otp')
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
