{{--
    Célébration de victoire : pluie de confettis + bonhomme qui danse + titre aux lettres qui sautent.
    À placer dans un conteneur « relative » (les confettis le recouvrent).
--}}
@props(['title' => 'BRAVOOO !', 'subtitle' => null])

@php $colors = ['#FF7900', '#111111', '#16A34A', '#FACC15', '#0072CE']; @endphp

{{--
    Confettis découpés dans le tissu Lui Lipendé : chaque morceau montre une partie différente
    du motif (décalage de l'image). Positions et durées variées mais déterministes (pas de JavaScript).
--}}
<div class="confetti-rain" aria-hidden="true">
    @for ($i = 0; $i < 28; $i++)
        <span style="left: {{ ($i * 37 + 7) % 100 }}%; background-color: {{ $colors[$i % 5] }};
                     background-position: -{{ ($i * 41) % 290 }}px -{{ ($i * 13) % 40 }}px;
                     width: {{ 14 + $i % 3 * 4 }}px; height: {{ 14 + $i % 4 * 3 }}px;
                     animation-delay: {{ number_format(fmod($i * 0.17, 1.6), 2) }}s;
                     animation-duration: {{ number_format(2.4 + ($i % 5) * 0.35, 2) }}s"></span>
    @endfor
</div>

<x-dancer class="relative mx-auto w-44 sm:w-52" />

{{-- Lettres collées (aucun espace entre les <span>) pour garder l'espacement normal du mot --}}
<p class="relative mt-1 text-4xl font-black text-om" aria-label="{{ $title }}">{!! collect(mb_str_split($title))
    ->map(fn ($char, $i) => '<span class="wave-letter" style="animation-delay: '.($i * 0.08).'s" aria-hidden="true">'.e($char === ' ' ? "\u{00A0}" : $char).'</span>')
    ->implode('') !!}</p>

@if ($subtitle)
    <p class="relative mt-1 text-lg font-bold">{{ $subtitle }}</p>
@endif
