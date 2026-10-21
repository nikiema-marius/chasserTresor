<x-layouts.app title="Règlement">
    <article class="card">
        <h1 class="text-2xl font-black">Règlement du jeu</h1>

        @if ($campaign)
            <p class="mt-1 text-sm text-gray-500">
                {{ $campaign->name }} — version {{ $campaign->regulation_version }}
            </p>
            <div class="prose-rules mt-4">
                {!! $campaign->regulationHtml() ?: '<p>Le règlement sera publié prochainement.</p>' !!}
            </div>

            <h2 class="mt-6 text-lg font-bold">Données personnelles</h2>
            <div class="prose-rules mt-2">
                <p>
                    Vos nom, prénom et numéro de téléphone sont utilisés uniquement pour l'organisation du jeu
                    et la remise des lots. Ils sont conservés au maximum {{ config('game.data_retention_days') }} jours
                    après la fin du jeu, puis anonymisés. Vous pouvez à tout moment consulter, exporter ou supprimer
                    vos données depuis la page « Mes données personnelles ».
                </p>
            </div>
        @else
            <p class="mt-4">Aucun jeu en cours.</p>
        @endif
    </article>

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn btn-ghost mt-4 w-full">Retour</a>
</x-layouts.app>
