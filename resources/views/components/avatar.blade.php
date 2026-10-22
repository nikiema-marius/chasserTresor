{{-- Pastille avec les initiales du participant --}}
@props(['participant', 'size' => 'size-11 text-base'])

<span {{ $attributes->class("grid {$size} shrink-0 place-items-center rounded-full border-2 border-ink bg-om font-black text-ink") }} aria-hidden="true">
    {{ mb_strtoupper(mb_substr($participant->pseudo, 0, 2)) }}
</span>
