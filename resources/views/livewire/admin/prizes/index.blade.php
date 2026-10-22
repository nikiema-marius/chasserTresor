<div>
    @php $tz = config('game.display_timezone'); @endphp
    <h1 class="text-2xl font-black">Remise des gains</h1>
    <p class="text-sm text-gray-500">{{ $campaign->name }} · chapiteau Orange Money</p>

    <div class="mt-4 grid grid-cols-2 gap-3 md:max-w-md">
        <x-stat label="Gagnants" :value="$totalWinners" />
        <x-stat label="Gains remis" :value="$totalClaimed" :hint="($totalWinners - $totalClaimed).' en attente'" />
    </div>

    <div class="card mt-4">
        <label for="search" class="label">Code de vérification affiché par le gagnant, ou son téléphone / nom</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" class="field font-mono text-xl tracking-widest uppercase" placeholder="Ex. 7K2QX9" autofocus autocomplete="off">
        <p class="mt-2 text-xs text-gray-500">
            Vérifiez sur le téléphone du gagnant que <strong>l'horloge défile</strong> (ce n'est pas une capture d'écran)
            et que le <strong>code</strong> correspond à celui ci-dessous.
        </p>
    </div>

    <div class="mt-4 grid gap-3">
        @forelse ($winners as $w)
            <div wire:key="w-{{ $w->id }}" @class(['card flex flex-wrap items-center justify-between gap-4', 'opacity-60' => $w->hasClaimedPrize()])>
                <div>
                    <p class="text-lg font-black">{{ $w->pseudo }}</p>
                    <p class="text-sm text-gray-600">{{ $w->masked_phone }} · toutes les lettres le {{ $w->solved_at->setTimezone($tz)->format('d/m à H:i') }}</p>
                    @unless ($w->isActive()) <p class="text-sm font-bold text-red-700">Participation {{ mb_strtolower($w->status->label()) }}</p> @endunless
                </div>
                <p class="font-mono text-2xl font-black tracking-[.25em] text-om-dark">{{ $w->prizeCode() }}</p>
                <div class="text-right">
                    @if ($w->hasClaimedPrize())
                        <p class="font-bold text-gray-700">✓ Remis le {{ $w->prize_claimed_at->setTimezone($tz)->format('d/m à H:i') }}</p>
                        <p class="text-xs text-gray-500">par {{ $admins[$w->prize_claimed_by] ?? '—' }}</p>
                        @can('super')
                            <button wire:click="unclaim({{ $w->id }})" wire:confirm="Annuler cette remise ?" class="mt-1 text-xs underline">Annuler la remise</button>
                        @endcan
                    @elseif ($w->isActive())
                        <button wire:click="claim({{ $w->id }})" wire:confirm="Confirmer la remise du gain à {{ $w->pseudo }} ?" class="btn btn-primary">Remettre le gain</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="card text-center text-gray-500">{{ $search ? 'Aucun gagnant ne correspond.' : 'Aucun gagnant pour l\'instant.' }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $winners->links() }}</div>
</div>
