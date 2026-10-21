<x-layouts.app>
    @php $hero = collect(['hero.webp', 'hero.jpg', 'hero.png'])->first(fn ($f) => file_exists(public_path('images/'.$f))); @endphp

    <section class="text-center">
        <h1 class="text-[2.1rem] leading-tight font-black">
            La chasse au trésor<br>du <span class="text-om">SIAO</span>
        </h1>
        <p class="mt-2 text-sm font-bold text-om">Explore • Scanne • Découvre • Gagne</p>
        <p class="mt-3 text-[15px] text-gray-700">
            Retrouve les QR codes cachés sur le site du SIAO, collecte les lettres
            et reconstitue le mot mystère pour tenter de gagner de super lots !
        </p>
    </section>

    {{-- Visuel : photo fournie (public/images/hero.jpg) ou illustration vectorielle légère --}}
    <div class="relative mt-5 overflow-hidden rounded-3xl bg-linear-to-b from-om-soft to-[#ffd9b3]">
        @if ($hero)
            <img src="{{ asset('images/'.$hero) }}" alt="Participante scannant un QR code au SIAO" class="h-64 w-full object-cover" loading="eager">
        @else
            <svg viewBox="0 0 360 230" class="h-auto w-full" role="img" aria-label="Téléphone scannant un QR code entouré de lettres">
                <defs>
                    <pattern id="bogolan" width="28" height="28" patternUnits="userSpaceOnUse">
                        <path d="M14 2 26 14 14 26 2 14z" fill="none" stroke="#7a3e1d" stroke-opacity=".12" stroke-width="2"/>
                    </pattern>
                </defs>
                <rect width="360" height="230" fill="url(#bogolan)"/>
                {{-- Étals d'artisanat --}}
                <path d="M0 230V150l40-30 40 30v80zM280 230v-90l40-28 40 28v90z" fill="#7a3e1d" fill-opacity=".18"/>
                <circle cx="60" cy="190" r="22" fill="#7a3e1d" fill-opacity=".25"/>
                <circle cx="305" cy="196" r="18" fill="#111" fill-opacity=".15"/>
                {{-- Téléphone --}}
                <g transform="rotate(-8 180 120)">
                    <rect x="128" y="28" width="104" height="188" rx="18" fill="#111"/>
                    <rect x="136" y="40" width="88" height="164" rx="10" fill="#fff"/>
                    <g fill="#111">
                        <rect x="150" y="62" width="22" height="22" rx="3"/><rect x="188" y="62" width="22" height="22" rx="3"/>
                        <rect x="150" y="100" width="22" height="22" rx="3"/><rect x="180" y="96" width="8" height="8"/>
                        <rect x="194" y="104" width="8" height="8"/><rect x="204" y="94" width="6" height="14"/>
                        <rect x="180" y="112" width="14" height="6"/>
                    </g>
                    <g fill="#fff"><rect x="156" y="68" width="10" height="10"/><rect x="194" y="68" width="10" height="10"/><rect x="156" y="106" width="10" height="10"/></g>
                    <path d="M146 56h10M146 56v10M214 56h-10M214 56v10M146 128h10M146 128v-10M214 128h-10M214 128v-10" stroke="#FF7900" stroke-width="3" stroke-linecap="round"/>
                    <rect x="146" y="146" width="68" height="16" rx="8" fill="#FF7900"/>
                    <rect x="150" y="172" width="18" height="18" rx="4" fill="#FF7900"/><rect x="171" y="172" width="18" height="18" rx="4" fill="#FF7900"/>
                    <rect x="192" y="172" width="18" height="18" rx="4" fill="none" stroke="#bbb" stroke-dasharray="3 2"/>
                </g>
                {{-- Lettres qui s'envolent --}}
                @foreach ([['A', 58, 58, -12], ['R', 268, 52, 10], ['T', 290, 120, -6], ['?', 40, 124, 8]] as [$l, $x, $y, $r])
                    <g transform="rotate({{ $r }} {{ $x + 18 }} {{ $y + 18 }})">
                        <rect x="{{ $x }}" y="{{ $y + 4 }}" width="36" height="36" rx="8" fill="#111"/>
                        <rect x="{{ $x }}" y="{{ $y }}" width="36" height="36" rx="8" fill="{{ $l === '?' ? '#fff' : '#FF7900' }}" stroke="#111" stroke-width="2"/>
                        <text x="{{ $x + 18 }}" y="{{ $y + 26 }}" text-anchor="middle" font-family="system-ui, sans-serif" font-size="22" font-weight="900" fill="#111">{{ $l }}</text>
                    </g>
                @endforeach
            </svg>
        @endif
    </div>

    @if ($campaign && $availability === \App\Enums\ScanResult::CampaignNotStarted)
        <div class="card mt-4 border-l-4 border-om text-center text-sm">
            <p class="font-semibold">Le jeu démarre le {{ $campaign->starts_at->setTimezone(config('game.display_timezone'))->translatedFormat('l j F à H\hi') }}.</p>
            <p class="mt-1 text-gray-600">Tu peux déjà t'inscrire.</p>
        </div>
    @elseif (! $campaign || $availability === \App\Enums\ScanResult::CampaignClosed)
        <div class="card mt-4 text-center text-sm font-semibold">
            {{ $campaign ? 'Le jeu est terminé. Merci à tous les participants !' : "Le jeu n'est pas encore ouvert. Reviens bientôt !" }}
        </div>
    @endif

    <div class="mt-5 grid gap-3">
        @auth('participant')
            <a href="{{ route('dashboard') }}" class="btn btn-primary w-full text-lg">Continuer ma partie <span aria-hidden="true">→</span></a>
        @else
            @if ($campaign && $availability !== \App\Enums\ScanResult::CampaignClosed)
                <a href="{{ route('register') }}" class="btn btn-primary w-full text-lg">Participer <span aria-hidden="true">→</span></a>
            @endif
            <a href="{{ route('regulation') }}" class="btn btn-ghost w-full">Voir le règlement complet</a>
            <p class="text-center text-sm">Déjà inscrit ? <a href="{{ route('login') }}" class="font-bold text-om-dark underline">Se connecter</a></p>
        @endauth
    </div>

    @if ($campaign?->rules_md)
        <section class="card mt-6">
            <h2 class="mb-3 text-lg font-black">Les règles</h2>
            <div class="prose-rules">{!! $campaign->rulesHtml() !!}</div>
        </section>
    @endif
</x-layouts.app>
