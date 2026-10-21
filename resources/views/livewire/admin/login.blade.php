<div class="card">
    <h1 class="text-xl font-black">Connexion</h1>

    @if ($step === 'credentials')
        <form wire:submit="authenticate" class="mt-4 grid gap-4">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" type="email" wire:model="email" class="field" autocomplete="username" required autofocus>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="label">Mot de passe</label>
                <input id="password" type="password" wire:model="password" class="field" autocomplete="current-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">Se connecter</button>
        </form>
    @else
        <form wire:submit="verifyCode" class="mt-4 grid gap-4">
            <p class="text-sm text-gray-700">Saisissez le code à 6 chiffres affiché dans votre application d'authentification.</p>
            <div>
                <label for="code" class="label">Code</label>
                <input id="code" type="text" wire:model="code" class="field text-center text-2xl tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">Vérifier</button>
        </form>
    @endif
</div>
