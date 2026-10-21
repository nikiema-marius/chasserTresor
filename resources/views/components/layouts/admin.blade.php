@props(['title' => null])
{{-- Layout de l'espace d'administration : barre latérale sombre (ordinateur), menu horizontal (mobile) --}}
@php
    $admin = auth('admin')->user();
    $current = \App\Models\Campaign::current() ?? \App\Models\Campaign::query()->latest('starts_at')->first();
    $links = array_values(array_filter([
        ['admin.dashboard', [], 'Tableau de bord', true, 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'],
        ['admin.campaigns', [], 'Campagnes', true, 'M4 4h16v16H4zM4 9h16M9 4v16'],
        $current ? ['admin.qr-codes', ['campaign' => $current], 'QR codes', $admin->can('manage'), 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2'] : null,
        $current ? ['admin.participants', ['campaign' => $current], 'Participants', true, 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-5-6'] : null,
        $current ? ['admin.prizes', ['campaign' => $current], 'Remise des gains', $admin->can('manage'), 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H8a2.5 2.5 0 1 1 0-5c3 0 4 5 4 5zm0 0h4a2.5 2.5 0 1 0 0-5c-3 0-4 5-4 5z'] : null,
        $current ? ['admin.rewards', ['campaign' => $current], 'Récompenses', $admin->can('super'), 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3'] : null,
        ['admin.audit', [], 'Journal', $admin->can('super'), 'M4 19V5M4 19h16M8 15l3-4 3 2 4-6'],
        ['admin.admins', [], 'Administrateurs', $admin->can('super'), 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z'],
    ], fn ($l) => $l && $l[3]));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Administration' }} · Admin SIAO</title>
    <link rel="icon" href="{{ asset('images/orange-money-dark.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-gray-100 text-ink antialiased lg:flex">
    {{-- Barre latérale --}}
    <aside class="bg-ink text-white lg:fixed lg:inset-y-0 lg:flex lg:w-60 lg:flex-col">
        <div class="flex items-center justify-between gap-3 px-4 py-4 lg:block">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/orange-money-white.png') }}" alt="Orange Money" class="h-10 w-auto" width="200" height="240">
                <span class="border-l border-white/20 pl-3 text-sm leading-tight font-bold">SIAO 2026<br><span class="text-xs font-normal text-white/60">Administration</span></span>
            </a>
        </div>

        <nav class="flex gap-1 overflow-x-auto px-3 pb-3 text-sm lg:flex-1 lg:flex-col lg:overflow-visible lg:pt-2" aria-label="Administration">
            @foreach ($links as [$route, $params, $label, , $icon])
                @php $active = request()->routeIs($route.'*'); @endphp
                <a href="{{ route($route, $params) }}"
                   @class(['flex shrink-0 items-center gap-3 rounded-lg px-3 py-2.5 font-medium whitespace-nowrap', 'bg-om text-ink' => $active, 'text-white/80 hover:bg-white/10 hover:text-white' => ! $active])>
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach

            <form method="POST" action="{{ route('admin.logout') }}" class="lg:mt-auto">
                @csrf
                <button class="flex w-full shrink-0 items-center gap-3 rounded-lg px-3 py-2.5 font-medium whitespace-nowrap text-white/80 hover:bg-white/10 hover:text-white">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3M10 17l-5-5 5-5M5 12h11"/></svg>
                    Déconnexion
                </button>
            </form>
        </nav>
        <div class="motif-band hidden lg:block" aria-hidden="true"></div>
    </aside>

    <div class="min-w-0 flex-1 lg:ml-60">
        {{-- Barre supérieure --}}
        <header class="flex items-center justify-end gap-3 border-b border-gray-200 bg-white px-6 py-3">
            <div class="text-right text-sm leading-tight">
                <p class="font-bold">{{ $admin->name }}</p>
                <p class="text-xs text-gray-500">{{ $admin->role->label() }}</p>
            </div>
            <span class="grid size-9 place-items-center rounded-full bg-om text-sm font-black text-ink" aria-hidden="true">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
        </header>

        {{-- Notifications des actions Livewire (événement « notify ») --}}
        <div x-data="{ items: [] }"
             x-on:notify.window="const item = { id: Date.now() + Math.random(), ...$event.detail }; items.push(item); setTimeout(() => items = items.filter(i => i.id !== item.id), item.type === 'error' ? 10000 : 5000)"
             class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
            <template x-for="item in items" :key="item.id">
                <div class="pointer-events-auto flex max-w-xl items-start gap-3 rounded-xl px-4 py-3 text-sm font-semibold shadow-lg"
                     :class="item.type === 'error' ? 'bg-red-700 text-white' : 'bg-ink text-white'" role="status">
                    <span x-text="item.message"></span>
                    <button type="button" class="ml-2 opacity-70 hover:opacity-100" x-on:click="items = items.filter(i => i.id !== item.id)" aria-label="Fermer">✕</button>
                </div>
            </template>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>

    @livewireScriptConfig(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
</body>
</html>
