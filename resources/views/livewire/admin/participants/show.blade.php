<div>
    @php $tz = config('game.display_timezone'); $threshold = config('game.suspicion.threshold'); @endphp

    <a href="{{ route('admin.participants', $participant->campaign_id) }}" class="text-sm underline">← Participants</a>
    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-black">{{ $participant->pseudo }}</h1>
        <span class="badge bg-gray-100">{{ $participant->status->label() }}</span>
        @if ($participant->anonymized_at) <span class="badge bg-gray-800 text-white">Anonymisé</span> @endif
        @if ($participant->suspicion_score >= $threshold) <span class="badge bg-red-100 text-red-900">Suspect ({{ $participant->suspicion_score }})</span> @endif
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card">
            <h2 class="font-bold">Identité</h2>
            <dl class="mt-3 grid gap-2 text-sm">
                <div><dt class="text-gray-500">Téléphone</dt><dd class="font-mono">{{ $canSeePhone ? $participant->phone_e164 : $participant->masked_phone }}</dd></div>
                <div><dt class="text-gray-500">Téléphone vérifié</dt><dd>{{ $participant->phone_verified_at ? 'Oui (SMS)' : 'Non (code secret)' }}</dd></div>
                <div><dt class="text-gray-500">Inscrit le</dt><dd>{{ $participant->created_at->setTimezone($tz)->format('d/m/Y H:i:s') }}</dd></div>
                <div><dt class="text-gray-500">Règlement</dt><dd>v{{ $participant->consent_rules_version }} · marketing : {{ $participant->consent_marketing ? 'oui' : 'non' }}</dd></div>
                <div><dt class="text-gray-500">IP d'inscription</dt><dd class="font-mono text-xs">{{ $participant->registration_ip ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="card">
            <h2 class="font-bold">Partie</h2>
            <dl class="mt-3 grid gap-2 text-sm">
                <div><dt class="text-gray-500">Lettres</dt><dd>{{ $participant->letters_count }} / {{ $participant->campaign->word_length }}</dd></div>
                <div><dt class="text-gray-500">Toutes les lettres le</dt><dd>{{ $participant->completed_at?->setTimezone($tz)->format('d/m/Y H:i:s.v') ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Mot trouvé le</dt><dd class="font-semibold">{{ $participant->solved_at?->setTimezone($tz)->format('d/m/Y H:i:s.v') ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Essais</dt><dd>{{ $participant->word_attempts }} / {{ $participant->allowedAttempts() }}</dd></div>
            </dl>
            <ul class="mt-3 grid gap-1 text-xs">
                @foreach ($participant->participantLetters as $pl)
                    <li>
                        <span class="font-mono font-bold">{{ $pl->campaignLetter->position }}·{{ $pl->campaignLetter->letter }}</span>
                        — {{ $pl->collected_at->setTimezone($tz)->format('d/m H:i:s') }} — QR #{{ $pl->qr_code_id }} ({{ $pl->qrCode->label }})
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card">
            <h2 class="font-bold">Suspicion</h2>
            @forelse ($participant->suspicion_flags ?? [] as $code => $flag)
                <p class="mt-2 text-sm"><span class="badge bg-red-100 text-red-900">{{ $code }}</span> {{ $flag['detail'] ?? '' }}</p>
            @empty
                <p class="mt-2 text-sm text-gray-500">Aucun signalement.</p>
            @endforelse

            @if ($sameDevice->isNotEmpty())
                <p class="mt-3 text-sm font-semibold">Même appareil :</p>
                <ul class="text-sm">
                    @foreach ($sameDevice as $other)
                        <li><a href="{{ route('admin.participants.show', $other) }}" class="underline">{{ $other->pseudo }} (#{{ $other->id }})</a></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @can('manage')
        @unless ($participant->anonymized_at)
            <section class="card mt-4">
                <h2 class="font-bold">Actions (journalisées)</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
                    <input type="text" wire:model="reason" class="field field-sm" maxlength="255" placeholder="Motif (recommandé)">
                    <div class="flex flex-wrap gap-2">
                        @if ($participant->status !== \App\Enums\ParticipantStatus::Active)
                            <button wire:click="setStatus('active')" class="btn btn-ghost btn-sm">Réactiver</button>
                        @endif
                        @if ($participant->status !== \App\Enums\ParticipantStatus::Blocked)
                            <button wire:click="setStatus('blocked')" wire:confirm="Bloquer ce participant ?" class="btn btn-ghost btn-sm">Bloquer</button>
                        @endif
                        @if ($participant->status !== \App\Enums\ParticipantStatus::Disqualified)
                            <button wire:click="setStatus('disqualified')" wire:confirm="Disqualifier ce participant ?" class="btn btn-danger btn-sm">Disqualifier</button>
                        @endif
                        @if ($participant->hasCompleted() && ! $participant->hasSolved())
                            <button wire:click="grantAttempt" wire:confirm="Accorder un essai supplémentaire ?" class="btn btn-ghost btn-sm">+1 essai</button>
                        @endif
                        @if ($participant->suspicion_score > 0)
                            <button wire:click="clearFlags" wire:confirm="Lever tous les signalements ?" class="btn btn-ghost btn-sm">Lever les signalements</button>
                        @endif
                        @can('super')
                            <button wire:click="anonymize" wire:confirm="Anonymiser définitivement ce participant ? Action irréversible." class="btn btn-danger btn-sm">Anonymiser (RGPD)</button>
                        @endcan
                    </div>
                </div>
            </section>
        @endunless
    @endcan

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card overflow-x-auto p-0">
            <h2 class="px-4 pt-4 font-bold">Derniers scans</h2>
            <table class="table mt-2">
                <thead><tr><th>Date</th><th>QR</th><th>Source</th><th>Résultat</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($scans as $scan)
                        <tr>
                            <td class="text-xs whitespace-nowrap">{{ $scan->created_at->setTimezone($tz)->format('d/m H:i:s') }}</td>
                            <td class="text-xs">{{ $scan->qrCode ? '#'.$scan->qr_code_id.' '.$scan->qrCode->label : '—' }}</td>
                            <td class="text-xs">{{ $scan->source->value }}</td>
                            <td><span @class(['badge', 'bg-green-100 text-green-900' => $scan->result->isSuccess(), 'bg-gray-100' => ! $scan->result->isSuccess()])>{{ $scan->result->value }}</span></td>
                            <td class="font-mono text-xs">{{ $scan->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-gray-500">Aucun scan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card overflow-x-auto p-0">
            <h2 class="px-4 pt-4 font-bold">Propositions de mot</h2>
            <table class="table mt-2">
                <thead><tr><th>#</th><th>Date</th><th>Proposition</th><th>Résultat</th></tr></thead>
                <tbody>
                    @forelse ($participant->mysteryAttempts as $a)
                        <tr>
                            <td>{{ $a->attempt_number }}</td>
                            <td class="text-xs">{{ $a->created_at->setTimezone($tz)->format('d/m H:i:s') }}</td>
                            <td class="font-mono">{{ $a->submitted }}</td>
                            <td>{{ $a->is_correct ? '✅' : '❌' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-500">Aucune proposition.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
