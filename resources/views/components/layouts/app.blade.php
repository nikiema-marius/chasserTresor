@props(['title' => null, 'immersive' => false])
{{--
    Layout participant : mobile-first, léger (logos optimisés, aucune police externe).
    - immersive : plein écran sans en-tête ni navigation (scanner)
--}}
@php
    $participant = auth('participant')->user();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $immersive ? '#000000' : '#FF7900' }}">
    <meta name="description" content="Scanne les QR codes du SIAO 2026, collecte les lettres et trouve le mot mystère avec Orange Money.">
    <title>{{ $title ? $title.' · ' : '' }}La chasse au trésor du SIAO</title>
    <link rel="icon" href="{{ asset('images/orange-money-dark.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body @class(['flex min-h-dvh flex-col antialiased', 'bg-black text-white' => $immersive, 'bg-sand text-ink' => ! $immersive])>
    @if ($immersive)
        <main class="flex flex-1 flex-col">{{ $slot }}</main>
    @else
        <header class="bg-white shadow-sm">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-3">
                <a href="{{ $participant ? route('dashboard') : route('home') }}" aria-label="Accueil">
                    <x-logos size="sm" />
                </a>
                @if ($participant)
                    <a href="{{ route('profile') }}" aria-label="Mon profil"><x-avatar :participant="$participant" size="size-9 text-sm" /></a>
                @endif
            </div>
        </header>

        <main class="w-full flex-1">
            <div class="mx-auto w-full max-w-md px-4 py-6">
                <x-flash />
                {{ $slot }}
            </div>
        </main>

        <footer class="text-xs text-gray-600">
            <div class="mx-auto flex max-w-md flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-3">
                <a href="{{ route('regulation') }}" class="underline">Règlement du jeu</a>
                <a href="{{ route('faq') }}" class="underline">Questions fréquentes</a>
                <span>© {{ now()->year }} Orange Money × SIAO</span>
            </div>
            <div class="motif-band" aria-hidden="true"></div>
        </footer>
    @endif

    @livewireScriptConfig(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
</body>
</html>
