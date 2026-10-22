{{--
    Jeux en ligne (plusieurs possibles en même temps), avec la progression du compte connecté.
    $games : collection de ['campaign' => Campaign, 'participation' => ?Participant]
--}}
@props(['games', 'current' => null])

@php $tz = config('game.display_timezone'); @endphp

<div {{ $attributes->class('grid gap-3') }}>
    @foreach ($games as ['campaign' => $game, 'participation' => $participation])
        @php
            $notStarted = $game->starts_at->isFuture();
            $isCurrent = $current && (int) $current->campaign_id === $game->id;
            $percent = $participation ? $participation->progressPercent() : 0;
        @endphp
        <div wire:key="game-{{ $game->id }}" @class(['card flex flex-col gap-3 border-l-8', 'ring-2 ring-ink' => $isCurrent]) style="border-left-color: {{ $game->color }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-lg font-black"><span class="size-3 shrink-0 rounded-full" style="background: {{ $game->color }}" aria-hidden="true"></span>{{ $game->name }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $game->word_length }} lettres à trouver ·
                        @if ($notStarted)
                            commence le {{ $game->starts_at->setTimezone($tz)->translatedFormat('j F à H\hi') }}
                        @else
                            jusqu'au {{ $game->ends_at->setTimezone($tz)->translatedFormat('j F') }}
                        @endif
                    </p>
                </div>
                @if ($isCurrent)
                    <span class="badge shrink-0 bg-om text-ink">En cours</span>
                @elseif ($participation?->hasSolved())
                    <span class="badge shrink-0 bg-green-100 text-green-900">🏆 Gagné</span>
                @endif
            </div>

            @if ($participation)
                <div>
                    <div class="flex justify-between text-xs font-semibold"><span>{{ $participation->letters_count }} / {{ $game->word_length }} lettres</span><span>{{ $percent }}%</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200"><div class="h-full rounded-full bg-om" style="width: {{ $percent }}%"></div></div>
                </div>
            @endif

            @auth('participant')
                @if ($participation)
                    @unless ($isCurrent)
                        <a href="{{ route('play', $game) }}" class="btn btn-dark btn-sm">{{ $participation->hasSolved() ? 'Voir mon gain' : 'Continuer ce jeu' }}</a>
                    @endunless
                @else
                    <a href="{{ route('join', $game) }}" class="btn btn-primary btn-sm">Rejoindre ce jeu</a>
                @endif
            @else
                <a href="{{ route('register', ['jeu' => $game->slug]) }}" class="btn btn-primary btn-sm">Participer <span aria-hidden="true">→</span></a>
            @endauth
        </div>
    @endforeach
</div>
