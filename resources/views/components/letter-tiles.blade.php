{{-- Tuiles de lettres groupées par mot (null = lettre à trouver) --}}
@props(['groups', 'small' => false])

<div {{ $attributes->class('flex flex-wrap justify-center gap-x-4 gap-y-2') }}>
    @foreach ($groups as $group)
        <div class="flex flex-wrap justify-center gap-1.5">
            @foreach ($group as $letter)
                <div @class(['tile', 'size-9 text-lg sm:size-10' => $small, 'tile-on' => $letter, 'tile-off' => ! $letter])
                     aria-label="{{ $letter ? 'Lettre '.$letter : 'Lettre à trouver' }}">{{ $letter ?? '?' }}</div>
            @endforeach
        </div>
    @endforeach
</div>
