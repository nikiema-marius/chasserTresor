<div>
    <h1 class="text-2xl font-black">{{ $campaign ? 'Modifier « '.$campaign->name.' »' : 'Nouvelle campagne' }}</h1>

    <form wire:submit="save" class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card grid gap-4">
            <h2 class="font-bold">Général</h2>
            <div>
                <label class="label" for="name">Nom</label>
                <input id="name" type="text" wire:model="name" class="field" maxlength="150">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="starts_at">Début (heure de Ouagadougou)</label>
                    <input id="starts_at" type="datetime-local" wire:model="starts_at" class="field">
                    @error('starts_at') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="ends_at">Fin</label>
                    <input id="ends_at" type="datetime-local" wire:model="ends_at" class="field">
                    @error('ends_at') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="daily_open_time">Ouverture quotidienne</label>
                    <input id="daily_open_time" type="time" wire:model="daily_open_time" class="field">
                    @error('daily_open_time') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="daily_close_time">Fermeture quotidienne</label>
                    <input id="daily_close_time" type="time" wire:model="daily_close_time" class="field">
                    @error('daily_close_time') <p class="error">{{ $message }}</p> @enderror
                </div>
                <p class="col-span-2 -mt-2 text-xs text-gray-500">Laissez les horaires vides pour autoriser les scans à toute heure.</p>
            </div>
        </section>

        <section class="card grid gap-4">
            <h2 class="font-bold">Mot mystère et règles du jeu</h2>
            <div>
                <label class="label" for="word">Mot mystère</label>
                @if ($campaign?->isWordLocked())
                    <p class="rounded-lg bg-gray-100 px-3 py-2 text-sm">
                        🔒 Verrouillé depuis le {{ $campaign->word_locked_at->setTimezone(config('game.display_timezone'))->format('d/m/Y H:i') }}
                        ({{ $campaign->word_length }} lettres) : des lettres ont déjà été collectées.
                    </p>
                @else
                    <input id="word" type="text" wire:model.live.debounce.300ms="word" class="field font-mono tracking-widest uppercase" maxlength="40" autocomplete="off"
                           placeholder="{{ $campaign ? 'Laisser vide pour ne pas changer ('.$campaign->word_length.' lettres)' : 'Ex. MAX IT, SIAO, ORANGE' }}">
                    <p class="mt-1 text-xs text-gray-500">
                        Un mot ou une expression ({{ config('game.word.min_length') }} à {{ config('game.word.max_length') }} lettres).
                        Les espaces séparent les mots ; les accents sont retirés. Le mot se verrouille au premier scan valide.
                    </p>

                    {{-- Aperçu : une tuile = une lettre = un groupe de QR codes --}}
                    @if ($preview)
                        <div class="mt-3 rounded-xl bg-gray-50 p-3">
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($preview as $part)
                                    <div class="flex gap-1">
                                        @foreach (str_split($part) as $letter)
                                            <span class="tile tile-on size-9 text-lg">{{ $letter }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-sm">
                                <strong>{{ $previewLetters }} lettres</strong>
                                @if ($qrPerLetter > 0)
                                    → <strong>{{ $previewLetters * $qrPerLetter }} QR codes</strong> seront générés ({{ $qrPerLetter }} par lettre).
                                @endif
                            </p>
                            @if ($campaign && $campaign->qrCodes()->exists())
                                <p class="mt-1 text-sm font-semibold text-red-700">
                                    ⚠ Changer les lettres supprime les {{ $campaign->qrCodes()->count() }} QR codes existants et en génère de nouveaux : réimprimez-les.
                                </p>
                            @endif
                        </div>
                    @endif
                @endif
                @error('word') <p class="error">{{ $message }}</p> @enderror
            </div>
            @unless ($campaign?->isWordLocked())
                <div class="max-w-xs">
                    <label class="label" for="qrPerLetter">QR codes à générer par lettre</label>
                    <input id="qrPerLetter" type="number" min="0" max="50" wire:model.live="qrPerLetter" class="field">
                    <p class="mt-1 text-xs text-gray-500">Plusieurs QR par lettre : plus de chances de les trouver, et indispensable en mode « premier arrivé ». 0 = aucun.</p>
                    @error('qrPerLetter') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="validation_mode">Mode de validation des QR</label>
                    <select id="validation_mode" wire:model="validation_mode" class="field">
                        @foreach (\App\Enums\ValidationMode::cases() as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="letters_display">Affichage des lettres</label>
                    <select id="letters_display" wire:model="letters_display" class="field">
                        @foreach (\App\Enums\LettersDisplay::cases() as $display)
                            <option value="{{ $display->value }}">{{ $display->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 rounded-xl bg-gray-50 p-3">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model.live="require_word_entry" class="mt-0.5 size-5 shrink-0 accent-om">
                        <span>
                            <strong>Demander de saisir le mot mystère</strong><br>
                            <span class="text-gray-600">Décoché : le participant gagne dès qu'il a toutes les lettres (écran « Bravooo » à présenter au chapiteau Orange Money).</span>
                        </span>
                    </label>
                </div>
                @if ($require_word_entry)
                    <div>
                        <label class="label" for="max_word_attempts">Tentatives pour le mot</label>
                        <input id="max_word_attempts" type="number" min="1" max="20" wire:model="max_word_attempts" class="field">
                        @error('max_word_attempts') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="label" for="min_seconds_between_scans">Délai min. entre 2 scans (s)</label>
                    <input id="min_seconds_between_scans" type="number" min="0" max="3600" wire:model="min_seconds_between_scans" class="field">
                    @error('min_seconds_between_scans') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="otp_required">Vérification SMS (OTP)</label>
                    <select id="otp_required" wire:model="otp_required" class="field">
                        <option value="inherit">Selon la configuration serveur ({{ $otpDefault ? 'activée' : 'désactivée' }})</option>
                        <option value="1">Obligatoire</option>
                        <option value="0">Désactivée (code PIN)</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="min_age">Âge minimum</label>
                    <input id="min_age" type="number" min="0" max="99" wire:model="min_age" class="field">
                </div>
            </div>
            <p class="text-xs text-gray-500">
                En mode « premier arrivé », chaque QR ne donne sa lettre qu'à une seule personne : prévoyez assez de QR par lettre
                pour que plusieurs participants puissent compléter le mot.
            </p>
        </section>

        <section class="card grid gap-4 lg:col-span-2">
            <h2 class="font-bold">Contenus (format Markdown)</h2>
            <div>
                <label class="label" for="rules_md">Règles affichées sur l'accueil</label>
                <textarea id="rules_md" wire:model="rules_md" rows="6" class="field py-3 font-mono text-sm"></textarea>
                @error('rules_md') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="regulation_md">Règlement complet</label>
                <textarea id="regulation_md" wire:model="regulation_md" rows="12" class="field py-3 font-mono text-sm"></textarea>
                @error('regulation_md') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="max-w-xs">
                <label class="label" for="regulation_version">Version du règlement</label>
                <input id="regulation_version" type="text" wire:model="regulation_version" class="field" maxlength="20">
                <p class="mt-1 text-xs text-gray-500">Changez-la à chaque modification : la version acceptée est enregistrée pour chaque inscrit.</p>
                @error('regulation_version') <p class="error">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex gap-3 lg:col-span-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer</button>
            <a href="{{ route('admin.campaigns') }}" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
