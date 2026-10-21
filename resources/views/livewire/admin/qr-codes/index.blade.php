<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">QR codes</h1>
            <p class="text-sm text-gray-500">{{ $campaign->name }} · mode par défaut : {{ $campaign->validation_mode->label() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.qr-codes.pdf', $campaign) }}" class="btn btn-dark btn-sm">PDF d'impression (A4)</a>
            <a href="{{ route('admin.qr-codes.zip', $campaign) }}" class="btn btn-ghost btn-sm">ZIP des images PNG</a>
        </div>
    </div>

    <p class="mt-3 rounded-lg bg-yellow-50 px-4 py-2 text-sm text-yellow-900">
        Les lettres ne sont jamais imprimées sur les QR codes. Seuls l'emplacement et le code de saisie manuelle apparaissent.
        Ne diffusez pas cette page : elle révèle quelle lettre correspond à chaque QR.
    </p>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <form wire:submit="generateBatch" class="card grid gap-3">
            <h2 class="font-bold">Générer en lot</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="perLetter">QR par position</label>
                    <input id="perLetter" type="number" min="1" max="50" wire:model="perLetter" class="field">
                    @error('perLetter') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="labelPrefix">Préfixe d'emplacement</label>
                    <input id="labelPrefix" type="text" wire:model="labelPrefix" class="field" maxlength="80">
                    @error('labelPrefix') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" wire:confirm="Générer {{ $campaign->word_length }} × N nouveaux QR codes ?">Générer</button>
        </form>

        <form wire:submit="addOne" class="card grid gap-3">
            <h2 class="font-bold">Ajouter un QR</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="newPosition">Position</label>
                    <select id="newPosition" wire:model="newPosition" class="field">
                        <option value="">—</option>
                        @foreach ($letters as $letter)
                            <option value="{{ $letter->position }}">{{ $letter->position }} ({{ $letter->letter }})</option>
                        @endforeach
                    </select>
                    @error('newPosition') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="newLabel">Emplacement physique</label>
                    <input id="newLabel" type="text" wire:model="newLabel" class="field" maxlength="120" placeholder="Pavillon A – Stand 12">
                    @error('newLabel') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
        </form>
    </div>

    <div class="mt-4 grid gap-4">
        @foreach ($letters as $letter)
            <section class="card overflow-x-auto p-0" wire:key="letter-{{ $letter->id }}">
                <h2 class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 font-bold">
                    <span class="tile tile-on size-9 text-lg">{{ $letter->letter }}</span>
                    Position {{ $letter->position }}
                    <span class="text-sm font-normal text-gray-500">— {{ $letter->qrCodes->where('status', \App\Enums\QrStatus::Active)->count() }} actif(s)</span>
                </h2>
                <table class="table">
                    <thead><tr><th>#</th><th>Emplacement</th><th>Code manuel</th><th>Mode</th><th>Statut</th><th class="text-right">Scans</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($letter->qrCodes as $qr)
                            <tr wire:key="qr-{{ $qr->id }}" @class(['opacity-50' => $qr->status === \App\Enums\QrStatus::Revoked])>
                                <td>{{ $qr->id }}</td>
                                <td class="min-w-48">
                                    @if ($editingId === $qr->id)
                                        <form wire:submit="saveLabel" class="flex gap-1">
                                            <input type="text" wire:model="editingLabel" class="field field-sm" maxlength="120" autofocus>
                                            <button class="btn btn-primary btn-sm">OK</button>
                                        </form>
                                        @error('editingLabel') <p class="error">{{ $message }}</p> @enderror
                                    @else
                                        <button type="button" wire:click="startEdit({{ $qr->id }})" class="text-left hover:underline" title="Modifier l'emplacement">{{ $qr->label }} ✎</button>
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $qr->formattedManualCode() }}</td>
                                <td>
                                    <select class="field field-sm w-auto" wire:change="setMode({{ $qr->id }}, $event.target.value)" @disabled($qr->status === \App\Enums\QrStatus::Revoked)>
                                        <option value="inherit" @selected(! $qr->validation_mode)>Campagne</option>
                                        @foreach (\App\Enums\ValidationMode::cases() as $mode)
                                            <option value="{{ $mode->value }}" @selected($qr->validation_mode === $mode)>{{ $mode === \App\Enums\ValidationMode::FirstCome ? 'Unique' : 'Multi' }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <span @class(['badge', 'bg-green-100 text-green-900' => $qr->status === \App\Enums\QrStatus::Active, 'bg-yellow-100 text-yellow-900' => $qr->status === \App\Enums\QrStatus::Disabled, 'bg-red-100 text-red-900' => $qr->status === \App\Enums\QrStatus::Revoked])>{{ $qr->status->label() }}</span>
                                    @if ($qr->revoked_reason) <p class="text-xs text-gray-500">{{ $qr->revoked_reason }}</p> @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $qr->valid_scans_count }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        @if ($qr->status !== \App\Enums\QrStatus::Revoked)
                                            <a href="{{ route('admin.qr-codes.image', [$qr, 'png']) }}" class="btn btn-ghost btn-sm">PNG</a>
                                            <a href="{{ route('admin.qr-codes.image', [$qr, 'svg']) }}" class="btn btn-ghost btn-sm">SVG</a>
                                            @if ($qr->status === \App\Enums\QrStatus::Active)
                                                <button wire:click="disable({{ $qr->id }})" class="btn btn-ghost btn-sm">Désactiver</button>
                                            @else
                                                <button wire:click="enable({{ $qr->id }})" class="btn btn-ghost btn-sm">Activer</button>
                                            @endif
                                            <button wire:click="regenerate({{ $qr->id }})" wire:confirm="Révoquer ce QR et en créer un nouveau (à réimprimer) ?" class="btn btn-ghost btn-sm">Régénérer</button>
                                            <button wire:click="revoke({{ $qr->id }})" wire:confirm="Révoquer définitivement ce QR code ?" class="btn btn-danger btn-sm">Révoquer</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-center text-sm text-red-700">Aucun QR code pour cette position : la lettre est impossible à obtenir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>

    <div class="card mt-4 max-w-md">
        <label class="label" for="revokeReason">Motif de révocation (facultatif, utilisé par le prochain « Révoquer »)</label>
        <input id="revokeReason" type="text" wire:model="revokeReason" class="field field-sm" maxlength="255" placeholder="QR photographié et partagé sur les réseaux…">
    </div>
</div>
