@props(['title' => null])
{{-- Layout des pages admin hors session (connexion, activation 2FA) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Administration' }} · Chasse au trésor SIAO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-ink text-ink antialiased">
    <div class="motif-band" aria-hidden="true"></div>
    <main class="mx-auto flex min-h-[90dvh] w-full max-w-sm flex-col justify-center px-4 py-10">
        <div class="mb-6 flex flex-col items-center gap-3">
            <img src="{{ asset('images/orange-money-white.png') }}" alt="Orange Money" class="h-16 w-auto" width="200" height="240">
            <p class="text-sm font-bold text-white">SIAO 2026 — Administration</p>
        </div>
        <x-flash />
        {{ $slot }}
    </main>
    @livewireScriptConfig(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
</body>
</html>
