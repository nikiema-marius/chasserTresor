{{-- Logos partenaires : Orange Money | SIAO 2026 --}}
@props(['variant' => 'dark', 'size' => 'md'])

@php
    $h = ['sm' => 'h-8', 'md' => 'h-10', 'lg' => 'h-12'][$size] ?? 'h-10';
@endphp

<div {{ $attributes->class('flex items-center gap-3') }}>
    <img src="{{ asset('images/orange-money-'.($variant === 'white' ? 'white' : 'dark').'.png') }}"
         alt="Orange Money" class="{{ $h }} w-auto" width="200" height="240">
    <span class="{{ $h }} w-px {{ $variant === 'white' ? 'bg-white/30' : 'bg-gray-300' }}" aria-hidden="true"></span>
    <span class="flex items-center gap-1.5">
        <img src="{{ asset('images/siao.jpg') }}" alt="SIAO" class="{{ $h }} w-auto rounded-sm" width="147" height="87">
        <span class="text-lg leading-none font-black {{ $variant === 'white' ? 'text-white' : 'text-ink' }}">2026</span>
    </span>
</div>
