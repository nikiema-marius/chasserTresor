<div class="card">
    <h1 class="text-xl font-black">Activer la double authentification</h1>
    <p class="mt-2 text-sm text-gray-700">
        Obligatoire pour les super-administrateurs. Scannez ce QR code avec une application
        d'authentification (Google Authenticator, Microsoft Authenticator, FreeOTP…).
    </p>

    <div class="mx-auto mt-4 w-fit rounded-xl bg-white p-2 ring-1 ring-gray-200">{!! $qrSvg !!}</div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer font-semibold">Saisie manuelle de la clé</summary>
        <p class="mt-2 font-mono text-xs break-all select-all">{{ $secret }}</p>
    </details>

    <form wire:submit="confirm" class="mt-4 grid gap-3">
        <label for="code" class="label">Code affiché par l'application</label>
        <input id="code" type="text" wire:model="code" class="field text-center text-2xl tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
        @error('code') <p class="error">{{ $message }}</p> @enderror
        <button type="submit" class="btn btn-primary w-full">Activer</button>
    </form>

    <form method="POST" action="{{ route('admin.logout') }}" class="mt-3 text-center">
        @csrf
        <button class="text-sm underline">Se déconnecter</button>
    </form>
</div>
