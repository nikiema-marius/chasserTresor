<div>
    <div class="text-center">
        <h1 class="text-2xl font-black">Connexion</h1>
        <p class="mt-1 text-sm text-gray-600">Retrouve ta partie avec le numéro utilisé à l'inscription.</p>
    </div>

    @if ($step === 'form')
        <form wire:submit="submit" class="mt-6 grid gap-3" novalidate>
            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
                    <div class="flex-1">
                        <label for="phone">Numéro de téléphone</label>
                        <input id="phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="+226 70 12 34 56" maxlength="25" required>
                    </div>
                </div>
                @error('phone') <p class="error">{{ $message }}</p> @enderror
                @if ($needsRegistration)
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm mt-2">Créer mon compte</a>
                @endif
            </div>

            @unless ($otpRequired)
                <div>
                    <div class="field-group">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <div class="flex-1">
                            <label for="pin">Code PIN</label>
                            <input id="pin" type="password" wire:model="pin" inputmode="numeric" maxlength="4" autocomplete="current-password" class="tracking-[.4em]" required>
                        </div>
                    </div>
                    @error('pin') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless

            <button type="submit" class="btn btn-primary mt-2 w-full text-lg" wire:loading.attr="disabled">
                {{ $otpRequired ? 'Recevoir mon code' : 'Se connecter' }}
            </button>
            <p class="text-center text-sm">Pas encore inscrit ? <a href="{{ route('register') }}" class="font-bold text-om-dark underline">Participer</a></p>
        </form>
    @else
        <form wire:submit="verifyOtp" class="mt-6 grid gap-4">
            <div>
                <div class="field-group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>
                    <div class="flex-1">
                        <label for="code">Code reçu par SMS</label>
                        <input id="code" type="text" wire:model="code" class="tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ config('game.otp.length') }}" autofocus required>
                    </div>
                </div>
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-full text-lg" wire:loading.attr="disabled">Valider</button>
            <button type="button" wire:click="backToForm" class="text-sm underline">Modifier le numéro</button>
        </form>
    @endif
</div>
