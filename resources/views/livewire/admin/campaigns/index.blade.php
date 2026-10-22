<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black">Campagnes</h1>
        @can('manage')
            <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary btn-sm">Nouvelle campagne</a>
        @endcan
    </div>

    @php $onlineCount = $campaigns->filter->isOnline()->count(); @endphp
    <p @class(['mt-4 rounded-lg border px-4 py-3 text-sm', 'border-green-300 bg-green-50 text-green-900' => $onlineCount, 'border-yellow-300 bg-yellow-50 text-yellow-900' => ! $onlineCount])>
        @if ($onlineCount)
            <strong>{{ $onlineCount }} jeu{{ $onlineCount > 1 ? 'x' : '' }} en ligne</strong> : les participants les voient tous sur l'accueil
            et chaque QR code les amène automatiquement sur le bon jeu.
        @else
            Aucun jeu en ligne : activez une campagne pour qu'elle apparaisse aux participants.
        @endif
    </p>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table">
            <thead>
                <tr><th>Campagne</th><th>Dates</th><th>Mot</th><th>Mode</th><th>QR</th><th>Inscrits</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    @php $problems = $campaign->status !== \App\Enums\CampaignStatus::Active ? $campaign->activationProblems() : []; @endphp
                    <tr wire:key="campaign-{{ $campaign->id }}">
                        <td>
                            <p class="flex items-center gap-2 font-semibold"><span class="size-3 shrink-0 rounded-full" style="background: {{ $campaign->color }}" aria-hidden="true"></span>{{ $campaign->name }}</p>
                            @if ($campaign->isOnline()) <span class="badge bg-om text-ink">En ligne</span> @endif
                        </td>
                        <td class="text-xs whitespace-nowrap">
                            {{ $campaign->starts_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') }}<br>
                            {{ $campaign->ends_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-xs">{{ $campaign->word_length }} lettres @if ($campaign->isWordLocked()) <span class="badge bg-gray-200">verrouillé</span> @endif</td>
                        <td class="text-xs">{{ $campaign->validation_mode->label() }}</td>
                        <td>{{ $campaign->qr_codes_count }}</td>
                        <td>{{ $campaign->participants_count }}</td>
                        <td>
                            <span @class(['badge', 'bg-green-100 text-green-900' => $campaign->status === \App\Enums\CampaignStatus::Active, 'bg-gray-100' => $campaign->status !== \App\Enums\CampaignStatus::Active])>{{ $campaign->status->label() }}</span>
                            @if ($problems)
                                <div class="mt-2 rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-800">
                                    <p class="font-bold">Pour pouvoir activer :</p>
                                    <ul class="list-disc pl-4">
                                        @foreach ($problems as $p) <li>{{ $p }}</li> @endforeach
                                    </ul>
                                    @can('manage')
                                        <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="mt-1 inline-block font-bold underline">Corriger</a>
                                    @endcan
                                </div>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="flex flex-wrap justify-end gap-1">
                                <a href="{{ route('admin.participants', $campaign) }}" class="btn btn-ghost btn-sm">Participants</a>
                                @can('manage')
                                    <a href="{{ route('admin.qr-codes', $campaign) }}" class="btn btn-ghost btn-sm">QR codes</a>
                                    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm">Modifier</a>
                                    <a href="{{ route('admin.prizes', $campaign) }}" class="btn btn-ghost btn-sm">Gains</a>
                                    @foreach (\App\Enums\CampaignStatus::cases() as $status)
                                        @if ($status !== $campaign->status && $status !== \App\Enums\CampaignStatus::Draft)
                                            <button wire:click="setStatus({{ $campaign->id }}, '{{ $status->value }}')" wire:confirm="Passer la campagne en « {{ $status->label() }} » ?" class="btn btn-sm {{ $status === \App\Enums\CampaignStatus::Active ? 'btn-primary' : 'btn-ghost' }}">{{ $status->label() }}</button>
                                        @endif
                                    @endforeach
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-gray-500">Aucune campagne.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
