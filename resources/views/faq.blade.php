<x-layouts.app title="Questions fréquentes">
    <h1 class="text-2xl font-black">Questions fréquentes</h1>

    @php
        $faq = [
            ['Comment jouer ?', "Inscris-toi, puis cherche les QR codes cachés sur le site du SIAO. Chaque QR code te donne une lettre. Quand tu as toutes les lettres, retrouve le mot mystère et valide-le."],
            ['Dois-je installer une application ?', "Non. Tout se passe dans ton navigateur (Chrome, Safari…). Tu peux aussi scanner les QR codes avec l'appareil photo de ton téléphone."],
            ['La caméra ne s\'ouvre pas, que faire ?', "Autorise l'accès à la caméra dans les réglages du navigateur. Sinon, ouvre l'appareil photo de ton téléphone et vise le QR code, ou saisis le code imprimé sous le QR code."],
            ['Je scanne un QR code mais il est refusé.', "Chaque QR code ne compte qu'une fois par participant. Si tu as déjà la lettre, cherche les autres QR codes. Le jeu n'est ouvert que pendant les horaires du salon."],
            ['J\'ai changé de téléphone, ai-je perdu ma partie ?', "Non. Ta progression est enregistrée. Clique sur « Se connecter » avec ton numéro de téléphone et ton code secret."],
            $campaign?->require_word_entry
                ? ['Combien d\'essais ai-je pour le mot mystère ?', 'Tu as '.$campaign->max_word_attempts.' essais. Les majuscules, les accents et les espaces ne comptent pas.']
                : ['Comment gagner ?', "Trouve toutes les lettres : l'application affiche « BRAVOOO ! » et le mot mystère."],
            ['Comment récupérer mon gain ?', "Rends-toi au chapiteau Orange Money et montre l'écran « Mon gain » de ton application (avec ton code de vérification). Chaque gain n'est remis qu'une seule fois, selon le règlement et dans la limite des lots disponibles."],
            ['Que deviennent mes données ?', 'Elles servent uniquement à organiser le jeu et sont anonymisées '.config('game.data_retention_days').' jours après la fin. Tu peux les télécharger ou les supprimer depuis ton profil.'],
        ];
    @endphp

    <div class="mt-4 grid gap-3">
        @foreach ($faq as [$question, $answer])
            <details class="card group p-0">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold">
                    {{ $question }}
                    <span class="text-om transition group-open:rotate-45" aria-hidden="true">＋</span>
                </summary>
                <p class="px-5 pb-4 text-sm text-gray-700">{{ $answer }}</p>
            </details>
        @endforeach
    </div>

    <a href="{{ route('regulation') }}" class="btn btn-ghost mt-6 w-full">Lire le règlement complet</a>
</x-layouts.app>
