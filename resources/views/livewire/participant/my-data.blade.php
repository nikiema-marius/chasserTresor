<div>
    <h1 class="text-2xl font-black">Mes données personnelles</h1>

    <section class="card mt-4">
        <dl class="grid gap-3 text-sm">
            <div><dt class="text-gray-500">Pseudo</dt><dd class="font-semibold">{{ $participant->pseudo }}</dd></div>
            <div><dt class="text-gray-500">Téléphone</dt><dd class="font-semibold">{{ \App\Support\Phone::display($participant->phone_e164) }}</dd></div>
            <div><dt class="text-gray-500">Inscrit le</dt><dd class="font-semibold">{{ $participant->created_at->setTimezone(config('game.display_timezone'))->translatedFormat('j F Y à H\hi') }}</dd></div>
            <div><dt class="text-gray-500">Règlement accepté</dt><dd class="font-semibold">Version {{ $participant->consent_rules_version }}</dd></div>
            <div><dt class="text-gray-500">Offres Orange Money par SMS</dt><dd class="font-semibold">{{ $participant->consent_marketing ? 'Acceptées' : 'Refusées' }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-gray-500">
            Ces données servent uniquement à l'organisation du jeu et à la remise des lots.
            Elles seront anonymisées {{ config('game.data_retention_days') }} jours après la fin du jeu.
        </p>
        <a href="{{ route('my-data.export') }}" class="btn btn-ghost mt-4 w-full">Télécharger mes données</a>
    </section>

    <section class="card mt-4 border-l-4 border-red-700">
        <h2 class="font-bold">Supprimer mes données</h2>
        <p class="mt-1 text-sm text-gray-700">
            Vos informations personnelles seront effacées définitivement.
            <strong>Vous perdrez votre participation et ne pourrez plus gagner.</strong>
        </p>
        <form wire:submit="deleteAccount" class="mt-3 grid gap-3">
            <label for="confirmation" class="label">Tapez SUPPRIMER pour confirmer</label>
            <input id="confirmation" type="text" wire:model="confirmation" class="field" autocomplete="off" autocapitalize="characters">
            @error('confirmation') <p class="error">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-danger w-full">Supprimer définitivement</button>
        </form>
    </section>
</div>
