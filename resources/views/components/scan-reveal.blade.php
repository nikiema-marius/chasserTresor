{{--
    Écran de résultat d'un scan (maquettes 5 et 6) : « Bravo ! » avec la lettre,
    ou « Déjà scanné ! » / autres refus. Les boutons sont fournis par le slot « actions ».
--}}
@props(['outcome', 'count', 'total'])

@php
    $result = \App\Enums\ScanResult::from($outcome['result']);
    $percent = $total > 0 ? (int) floor($count * 100 / $total) : 0;
    $warning = in_array($result, [\App\Enums\ScanResult::AlreadyOwnedQr, \App\Enums\ScanResult::AlreadyOwnedSlot, \App\Enums\ScanResult::TooFast], true);
    $confetti = [
        ['8%', '12%', '#FF7900', '-20deg'], ['22%', '4%', '#111', '35deg'], ['80%', '6%', '#FF7900', '15deg'],
        ['92%', '18%', '#16a34a', '-40deg'], ['12%', '32%', '#16a34a', '60deg'], ['86%', '34%', '#FF7900', '-10deg'],
        ['6%', '52%', '#FF7900', '25deg'], ['94%', '50%', '#111', '-30deg'],
    ];
@endphp

<div {{ $attributes->class('relative flex min-h-full flex-col items-center px-6 pt-12 pb-8 text-center') }} role="status">
    @if ($outcome['success'] && ! empty($outcome['solved']))
        {{-- Dernière lettre : le jeu est gagné, le bonhomme danse --}}
        <x-celebration title="BRAVOOO !" subtitle="Tu as toutes les lettres !" />

        @if ($outcome['letter'])
            <p class="relative mt-4 text-sm text-gray-600">Dernière lettre trouvée :</p>
            <div class="animate-letter-pop relative mt-1 grid size-16 place-items-center rounded-2xl bg-om text-4xl font-black text-white shadow-[0_5px_0_0_var(--color-om-dark)]">
                {{ $outcome['letter'] }}
            </div>
        @endif

        <p class="relative mt-6 text-gray-700">
            Tu as gagné ! Va récupérer ton gain au <strong>chapiteau Orange Money</strong> en montrant ton justificatif.
        </p>
    @elseif ($outcome['success'])
        @foreach ($confetti as $i => [$left, $top, $color, $rotate])
            <span class="confetti" style="left: {{ $left }}; top: {{ $top }}; background-color: {{ $color }}; background-position: -{{ ($i * 47) % 290 }}px -{{ ($i * 11) % 40 }}px; rotate: {{ $rotate }}; animation-delay: {{ $i * 60 }}ms" aria-hidden="true"></span>
        @endforeach

        <p class="text-4xl font-black text-om">Bravo !</p>
        <p class="mt-1 text-xl font-bold">{{ $outcome['completed'] ? 'Tu as toutes les lettres !' : 'Tu as trouvé une lettre !' }}</p>

        @if ($outcome['letter'])
            <div class="animate-letter-pop mt-8 grid size-32 place-items-center rounded-3xl bg-om text-7xl font-black text-white shadow-[0_8px_0_0_var(--color-om-dark)]">
                {{ $outcome['letter'] }}
            </div>
        @endif

        <p class="mt-8 text-gray-700">
            @if ($outcome['completed'])
                Tu peux maintenant découvrir le mot mystère !
            @else
                Une nouvelle lettre vient d'être ajoutée à ta collection.
            @endif
        </p>
    @else
        <div @class(['mt-6 grid size-24 place-items-center rounded-full text-6xl font-black text-white', 'bg-om' => $warning, 'bg-red-600' => ! $warning]) aria-hidden="true">!</div>
        <p class="mt-6 text-2xl font-black">{{ $result->title() }}</p>
        <p class="mt-3 text-gray-700">{{ $outcome['message'] }}</p>
    @endif

    <div class="card relative z-10 mt-8 w-full text-left">
        <div class="flex items-baseline justify-between">
            <p class="font-bold">Ta progression</p>
            <p class="text-sm font-bold tabular-nums">{{ $percent }}%</p>
        </div>
        <p class="mt-1 text-lg font-black tabular-nums">{{ $count }} / {{ $total }}</p>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-om" style="width: {{ $percent }}%"></div>
        </div>
    </div>

    <div class="relative z-10 mt-8 grid w-full gap-3">
        {{ $actions }}
    </div>
</div>
