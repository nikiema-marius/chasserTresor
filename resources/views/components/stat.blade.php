{{-- Carte de statistique avec icône (tableau de bord admin) --}}
@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'om'])

<div class="card p-4">
    <p class="text-xs font-semibold text-gray-500">{{ $label }}</p>
    <div class="mt-2 flex items-center gap-2">
        @if ($icon)
            <svg @class(['size-6 shrink-0', 'text-om' => $tone === 'om', 'text-green-600' => $tone === 'green', 'text-blue-600' => $tone === 'blue']) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
        @endif
        <p class="text-2xl font-black tabular-nums">{{ $value }}</p>
    </div>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
