<div>
    <h1 class="text-2xl font-black">Administrateurs</h1>

    @if ($generatedPassword)
        <div class="card mt-4 border-l-4 border-om">
            <p class="font-bold">Compte créé. Mot de passe initial (affiché une seule fois) :</p>
            <p class="mt-2 rounded bg-gray-100 px-3 py-2 font-mono break-all select-all">{{ $generatedPassword }}</p>
            <p class="mt-2 text-xs text-gray-600">Transmettez-le par un canal sûr. Un super-admin devra activer la double authentification à sa première connexion.</p>
            <button wire:click="dismissPassword" class="btn btn-ghost btn-sm mt-3">J'ai noté le mot de passe</button>
        </div>
    @endif

    <form wire:submit="create" class="card mt-4 grid gap-3 md:grid-cols-4">
        <div>
            <label class="label" for="name">Nom</label>
            <input id="name" type="text" wire:model="name" class="field field-sm" maxlength="120">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" wire:model="email" class="field field-sm" maxlength="190">
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="role">Rôle</label>
            <select id="role" wire:model="role" class="field field-sm">
                @foreach (\App\Enums\AdminRole::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end"><button type="submit" class="btn btn-primary btn-sm w-full">Créer le compte</button></div>
    </form>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>2FA</th><th>Dernière connexion</th><th>Actif</th><th></th></tr></thead>
            <tbody>
                @foreach ($admins as $a)
                    <tr wire:key="admin-{{ $a->id }}" @class(['opacity-50' => ! $a->is_active])>
                        <td class="font-semibold">{{ $a->name }} @if ($a->id === $me->id) <span class="badge bg-om">vous</span> @endif</td>
                        <td>{{ $a->email }}</td>
                        <td>
                            @if ($a->id === $me->id)
                                {{ $a->role->label() }}
                            @else
                                <select class="field field-sm w-auto" wire:change="changeRole({{ $a->id }}, $event.target.value)">
                                    @foreach (\App\Enums\AdminRole::cases() as $r)
                                        <option value="{{ $r->value }}" @selected($a->role === $r)>{{ $r->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td>{{ $a->two_factor_confirmed_at ? '✅' : '—' }}</td>
                        <td class="text-xs">{{ $a->last_login_at?->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') ?? 'jamais' }} <span class="font-mono">{{ $a->last_login_ip }}</span></td>
                        <td>{{ $a->is_active ? 'Oui' : 'Non' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @unless ($a->id === $me->id)
                                <button wire:click="toggleActive({{ $a->id }})" wire:confirm="Confirmer ?" class="btn btn-ghost btn-sm">{{ $a->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                @if ($a->two_factor_confirmed_at)
                                    <button wire:click="resetTwoFactor({{ $a->id }})" wire:confirm="Réinitialiser la 2FA de ce compte ?" class="btn btn-ghost btn-sm">Réinit. 2FA</button>
                                @endif
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
