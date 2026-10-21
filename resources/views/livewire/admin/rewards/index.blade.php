<div>
    @php $tz = config('game.display_timezone'); @endphp
    <h1 class="text-2xl font-black">Récompenses</h1>
    <p class="text-sm text-gray-500">{{ $campaign->name }} · {{ $eligibleCount }} participant(s) admissible(s) actuellement</p>

    <p class="mt-3 rounded-lg bg-yellow-50 px-4 py-2 text-sm text-yellow-900">
        Aucune attribution n'est automatique : générez une proposition, vérifiez chaque gagnant (fiche participant, suspicion),
        puis validez explicitement. Un participant ne peut gagner qu'un seul lot.
    </p>

    <form wire:submit="propose" class="card mt-4 grid gap-3 md:grid-cols-5">
        <div>
            <label class="label" for="mode">Mécanisme</label>
            <select id="mode" wire:model="mode" class="field field-sm">
                @foreach (\App\Enums\RewardMode::cases() as $m)
                    <option value="{{ $m->value }}">{{ $m->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="winnersCount">Nombre de gagnants</label>
            <input id="winnersCount" type="number" min="1" max="500" wire:model="winnersCount" class="field field-sm">
            @error('winnersCount') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="prizeLabel">Lot</label>
            <input id="prizeLabel" type="text" wire:model="prizeLabel" class="field field-sm" maxlength="150" placeholder="Ex. 50 000 F CFA Orange Money">
        </div>
        <div>
            <label class="label" for="solvedBefore">Mot trouvé avant le</label>
            <input id="solvedBefore" type="datetime-local" wire:model="solvedBefore" class="field field-sm">
        </div>
        <div class="flex flex-col justify-end gap-2">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="excludeSuspects" class="size-4 accent-om"> Exclure les suspects</label>
            <button type="submit" class="btn btn-primary btn-sm">Générer une proposition</button>
        </div>
        <p class="text-xs text-gray-500 md:col-span-5">
            <strong>Premiers arrivés</strong> : ordre de réussite (à la milliseconde).
            <strong>Classement</strong> : temps le plus court entre inscription et réussite.
            <strong>Tirage</strong> : aléatoire et vérifiable (graine conservée).
        </p>
    </form>

    @foreach ($draws as $draw)
        <section class="card mt-4 overflow-x-auto p-0" wire:key="draw-{{ $draw->id }}">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <h2 class="font-bold">#{{ $draw->id }} · {{ $draw->mode->label() }} {{ $draw->prize_label ? '— '.$draw->prize_label : '' }}</h2>
                    <p class="text-xs text-gray-500">
                        Créée par {{ $draw->creator->name }} le {{ $draw->created_at->setTimezone($tz)->format('d/m/Y H:i') }}
                        · {{ $draw->eligibility['eligible_count'] ?? '?' }} admissible(s)
                        @if ($draw->validated_at) · validée par {{ $draw->validator->name }} le {{ $draw->validated_at->setTimezone($tz)->format('d/m/Y H:i') }} @endif
                    </p>
                    @if ($draw->seed) <p class="font-mono text-[10px] break-all text-gray-400">Graine : {{ $draw->seed }}</p> @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span @class(['badge', 'bg-yellow-100 text-yellow-900' => $draw->status === \App\Enums\RewardDrawStatus::Proposed, 'bg-green-100 text-green-900' => $draw->status === \App\Enums\RewardDrawStatus::Validated, 'bg-gray-200' => $draw->status === \App\Enums\RewardDrawStatus::Cancelled])>{{ $draw->status->value }}</span>
                    @if ($draw->status === \App\Enums\RewardDrawStatus::Proposed)
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="confirmValidation" class="accent-om"> J'ai vérifié la liste</label>
                        <button wire:click="validateDraw({{ $draw->id }})" class="btn btn-primary btn-sm">Valider</button>
                        <button wire:click="cancelDraw({{ $draw->id }})" wire:confirm="Annuler cette proposition ?" class="btn btn-ghost btn-sm">Annuler</button>
                    @endif
                </div>
            </div>
            @error('confirmValidation') <p class="error px-4">{{ $message }}</p> @enderror

            <table class="table">
                <thead><tr><th>Rang</th><th>Participant</th><th>Téléphone</th><th>Mot trouvé le</th><th>Durée</th><th>Suspicion</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @foreach ($draw->winners as $w)
                        <tr wire:key="w-{{ $w->id }}">
                            <td class="font-bold">{{ $w->rank }}</td>
                            <td><a href="{{ route('admin.participants.show', $w->participant) }}" class="underline">{{ $w->participant->full_name }}</a></td>
                            <td class="font-mono text-xs">{{ $w->participant->masked_phone }}</td>
                            <td class="text-xs">{{ $w->participant->solved_at?->setTimezone($tz)->format('d/m H:i:s.v') }}</td>
                            <td class="text-xs">{{ $w->participant->solved_at ? $w->participant->created_at->diffForHumans($w->participant->solved_at, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 2) : '' }}</td>
                            <td>{{ $w->participant->suspicion_score ?: '' }}</td>
                            <td><span class="badge bg-gray-100">{{ $w->status->label() }}</span> @if ($w->rejection_reason) <span class="text-xs text-gray-500">{{ $w->rejection_reason }}</span> @endif</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($draw->status === \App\Enums\RewardDrawStatus::Validated)
                                    @if (in_array($w->status, [\App\Enums\WinnerStatus::Validated], true))
                                        <button wire:click="setWinnerStatus({{ $w->id }}, 'notified')" class="btn btn-ghost btn-sm">Notifié</button>
                                    @endif
                                    @if (in_array($w->status, [\App\Enums\WinnerStatus::Validated, \App\Enums\WinnerStatus::Notified], true))
                                        <button wire:click="setWinnerStatus({{ $w->id }}, 'delivered')" wire:confirm="Confirmer la remise du lot ?" class="btn btn-primary btn-sm">Lot remis</button>
                                    @endif
                                @endif
                                @if (! in_array($w->status, [\App\Enums\WinnerStatus::Rejected, \App\Enums\WinnerStatus::Delivered], true) && $draw->status !== \App\Enums\RewardDrawStatus::Cancelled)
                                    <button wire:click="setWinnerStatus({{ $w->id }}, 'rejected')" class="btn btn-ghost btn-sm" title="Saisir le motif ci-dessous d'abord">Rejeter</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @if ($draws->isNotEmpty())
        <div class="card mt-4 grid max-w-xl gap-2">
            <label class="label" for="rejectionReason">Motif de rejet (utilisé par le prochain « Rejeter »)</label>
            <input id="rejectionReason" type="text" wire:model="rejectionReason" class="field field-sm" maxlength="255">
            @error('rejectionReason') <p class="error">{{ $message }}</p> @enderror
            <a href="{{ route('admin.exports.winners', [$campaign, 'complet' => 1]) }}" class="btn btn-ghost btn-sm mt-2 w-fit">Exporter les gagnants (CSV)</a>
        </div>
    @endif
</div>
