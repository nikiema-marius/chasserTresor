<div wire:poll.30s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-black">Tableau de bord</h1>
        @if ($campaigns->isNotEmpty())
            <select wire:model.live="campaignId" class="field field-sm w-auto" aria-label="Campagne">
                @foreach ($campaigns as $c)
                    <option value="{{ $c->id }}" @selected($campaign?->id === $c->id)>{{ $c->name }}{{ $c->is_current ? ' (publique)' : '' }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if (! $campaign)
        <div class="card mt-6">
            <p>Aucune campagne. <a href="{{ route('admin.campaigns.create') }}" class="font-bold underline">Créer une campagne</a></p>
        </div>
    @else
        <p class="mt-1 text-sm text-gray-500">
            {{ $campaign->status->label() }} ·
            du {{ $campaign->starts_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') }}
            au {{ $campaign->ends_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') }}
            · mis à jour {{ $stats['computed_at']->setTimezone(config('game.display_timezone'))->format('H:i:s') }}
        </p>

        @php
            $n = fn ($v) => number_format($v, 0, ',', ' ');
            $activeQr = collect($stats['qr'])->where('status', \App\Enums\QrStatus::Active->label())->count();
            $users = 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-5-6';
        @endphp
        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-stat label="Total inscrits" :value="$n($stats['registered'])" :icon="$users" tone="blue" />
            <x-stat label="Participants actifs" :value="$n($stats['active'])" :hint="$stats['conversion']['active'].' % des inscrits'" :icon="$users" tone="green" />
            <x-stat label="Total de scans" :value="$n($stats['scans_total'])" icon="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10" tone="blue" />
            <x-stat label="Scans valides" :value="$n($stats['scans_valid'])" :hint="$stats['conversion']['valid_scans'].' % des scans'" icon="M5 13l4 4L19 7" tone="green" />
            <x-stat label="QR codes actifs" :value="$activeQr.' / '.count($stats['qr'])" icon="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z" />
            <x-stat label="Mots mystères validés" :value="$n($stats['solved'])" :hint="$stats['conversion']['solved'].' % des inscrits'" icon="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z" />
        </div>

        {{-- Évolution des inscriptions (14 jours) : courbe SVG calculée côté serveur, aucune librairie --}}
        @php
            $daily = $stats['daily'];
            $max = max(1, ...array_values($daily));
            $w = 700; $h = 180; $pad = 24; $count = count($daily);
            $points = collect(array_values($daily))->map(fn ($v, $i) => [
                round($pad + $i * ($w - 2 * $pad) / max(1, $count - 1), 1),
                round($h - $pad - $v * ($h - 2 * $pad) / $max, 1),
            ]);
            $line = $points->map(fn ($p) => implode(',', $p))->implode(' ');
            $area = $pad.','.($h - $pad).' '.$line.' '.($w - $pad).','.($h - $pad);
        @endphp
        <section class="card mt-4">
            <div class="flex items-baseline justify-between">
                <h2 class="font-bold">Évolution des participations</h2>
                <p class="text-xs text-gray-500">Inscriptions par jour · 14 derniers jours</p>
            </div>
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="mt-3 h-auto w-full" role="img" aria-label="Courbe des inscriptions par jour">
                <defs>
                    <linearGradient id="area" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#FF7900" stop-opacity=".35"/><stop offset="1" stop-color="#FF7900" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                @foreach ([0, .5, 1] as $f)
                    <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $pad + $f * ($h - 2 * $pad) }}" y2="{{ $pad + $f * ($h - 2 * $pad) }}" stroke="#e5e7eb" stroke-dasharray="4 4"/>
                @endforeach
                <polygon points="{{ $area }}" fill="url(#area)"/>
                <polyline points="{{ $line }}" fill="none" stroke="#FF7900" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                @foreach ($points as $i => [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#fff" stroke="#FF7900" stroke-width="2.5"><title>{{ array_keys($daily)[$i] }} : {{ array_values($daily)[$i] }}</title></circle>
                @endforeach
            </svg>
            <div class="mt-1 flex justify-between text-[10px] text-gray-500">
                @foreach (array_keys($daily) as $i => $day)
                    <span @class(['hidden sm:inline' => $i % 2 === 1])>{{ \Carbon\Carbon::parse($day)->format('d/m') }}</span>
                @endforeach
            </div>
        </section>

        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-stat label="Toutes les lettres" :value="$n($stats['completed'])" :hint="$stats['conversion']['completed'].' % des inscrits'" />
            <x-stat label="Réussite (parmi complets)" :value="$stats['conversion']['solved_of_completed'].' %'" />
            <x-stat label="Participants suspects" :value="$stats['suspects']" />
            <x-stat label="Consentement marketing" :value="$stats['marketing']" />
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="card">
                <h2 class="font-bold">Lettres collectées par position</h2>
                @php $maxPos = max(1, ...array_column($stats['per_position'], 'count')); @endphp
                <ul class="mt-3 grid gap-2">
                    @foreach ($stats['per_position'] as $row)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-12 font-mono">{{ $row['position'] }} · {{ $row['letter'] }}</span>
                            <span class="h-4 flex-1 overflow-hidden rounded bg-gray-100"><span class="block h-full bg-om" style="width: {{ round($row['count'] * 100 / $maxPos) }}%"></span></span>
                            <span class="w-12 text-right tabular-nums">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card">
                <h2 class="font-bold">Résultats des scans</h2>
                @php $maxRes = max(1, ...array_values($stats['by_result'] ?: [0])); @endphp
                <ul class="mt-3 grid gap-2">
                    @forelse ($stats['by_result'] as $result => $count)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-40 truncate">{{ $result }}</span>
                            <span class="h-4 flex-1 overflow-hidden rounded bg-gray-100"><span @class(['block h-full', 'bg-green-600' => $result === 'valid', 'bg-gray-400' => $result !== 'valid']) style="width: {{ round($count * 100 / $maxRes) }}%"></span></span>
                            <span class="w-12 text-right tabular-nums">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Aucun scan pour l'instant.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card">
                <h2 class="font-bold">Scans valides — dernières 24 h</h2>
                @php $maxH = max(1, ...array_values($stats['hourly'] ?: [0])); @endphp
                <div class="mt-3 flex h-32 items-end gap-1">
                    @forelse ($stats['hourly'] as $hour => $count)
                        <div class="flex flex-1 flex-col items-center gap-1" title="{{ $hour }} : {{ $count }}">
                            <div class="w-full rounded-t bg-om" style="height: {{ max(4, round($count * 100 / $maxH)) }}%"></div>
                            <span class="text-[10px] text-gray-500">{{ substr($hour, 11, 2) }}h</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Aucun scan valide sur les dernières 24 h.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h2 class="font-bold">QR codes</h2>
                <p class="text-xs text-gray-500">Un QR jamais scanné est peut-être mal placé ou abîmé.</p>
                <div class="mt-3 max-h-72 overflow-y-auto">
                    <table class="table">
                        <thead><tr><th>#</th><th>Emplacement</th><th>Pos.</th><th>Statut</th><th class="text-right">Scans</th></tr></thead>
                        <tbody>
                            @foreach ($stats['qr'] as $qr)
                                <tr @class(['bg-yellow-50' => $qr['count'] === 0])>
                                    <td>{{ $qr['id'] }}</td><td>{{ $qr['label'] }}</td><td>{{ $qr['position'] }}</td>
                                    <td>{{ $qr['status'] }}</td><td class="text-right tabular-nums">{{ $qr['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @can('super')
            <section class="card mt-6">
                <h2 class="font-bold">Exports CSV</h2>
                <p class="text-xs text-gray-500">Chaque export est journalisé. Les numéros sont masqués sauf export « complet ».</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.exports.participants', $campaign) }}" class="btn btn-ghost btn-sm">Participants</a>
                    <a href="{{ route('admin.exports.participants', [$campaign, 'complet' => 1]) }}" class="btn btn-ghost btn-sm">Participants (téléphones complets)</a>
                    <a href="{{ route('admin.exports.participants', [$campaign, 'marketing' => 1, 'complet' => 1]) }}" class="btn btn-ghost btn-sm">Consentements marketing</a>
                    <a href="{{ route('admin.exports.scans', $campaign) }}" class="btn btn-ghost btn-sm">Scans</a>
                    <a href="{{ route('admin.exports.winners', [$campaign, 'complet' => 1]) }}" class="btn btn-ghost btn-sm">Gagnants</a>
                </div>
            </section>
        @endcan
    @endif
</div>
