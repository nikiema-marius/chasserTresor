{{--
    Bonhomme qui danse (écran de victoire) : SVG + animations CSS (voir .dancer-* dans app.css).
    Tunique orange à rayures Faso Dan Fani, casquette Orange. Aucun fichier externe, < 5 Ko.
    Les animations s'arrêtent si l'utilisateur a demandé à réduire les mouvements.
--}}
@php $clip = 'dancer-tunic-'.uniqid(); @endphp

<svg {{ $attributes->class('dancer') }} viewBox="0 0 200 240" role="img" aria-label="Un personnage danse de joie">
    <defs>
        <clipPath id="{{ $clip }}">
            <path d="M70 92 Q100 80 130 92 L138 158 Q100 168 62 158 Z"/>
        </clipPath>
    </defs>

    {{-- Étoiles scintillantes --}}
    @foreach ([[28, 46, '#FF7900', 0], [176, 38, '#FACC15', .3], [18, 150, '#16A34A', .6], [184, 132, '#FF7900', .9]] as [$x, $y, $fill, $delay])
        <g transform="translate({{ $x }} {{ $y }})">
            <path class="dancer-spark" style="animation-delay: {{ $delay }}s" d="M0 -9 L2.5 -2.5 L9 0 L2.5 2.5 L0 9 L-2.5 2.5 L-9 0 L-2.5 -2.5 Z" fill="{{ $fill }}"/>
        </g>
    @endforeach

    {{-- Ombre au sol --}}
    <ellipse class="dancer-shadow" cx="100" cy="228" rx="42" ry="7" fill="#000"/>

    <g class="dancer-bounce">
        {{-- Jambes --}}
        <g class="dancer-leg-l">
            <rect x="78" y="148" width="16" height="58" rx="8" fill="#111"/>
            <ellipse cx="84" cy="210" rx="13" ry="7" fill="#FF7900" stroke="#111" stroke-width="2.5"/>
        </g>
        <g class="dancer-leg-r">
            <rect x="106" y="148" width="16" height="58" rx="8" fill="#111"/>
            <ellipse cx="116" cy="210" rx="13" ry="7" fill="#FF7900" stroke="#111" stroke-width="2.5"/>
        </g>

        <g class="dancer-body">
            {{-- Tunique avec rayures tissées --}}
            <path d="M70 92 Q100 80 130 92 L138 158 Q100 168 62 158 Z" fill="#FF7900"/>
            <g clip-path="url(#{{ $clip }})">
                <rect x="55" y="110" width="90" height="7" fill="#111"/>
                <rect x="55" y="120" width="90" height="3" fill="#fff"/>
                <rect x="55" y="126" width="90" height="7" fill="#111"/>
                @foreach ([72, 86, 100, 114, 128] as $dx)
                    <path d="M{{ $dx }} 140 l4 5 -4 5 -4 -5z" fill="#fff"/>
                @endforeach
            </g>
            <path d="M70 92 Q100 80 130 92 L138 158 Q100 168 62 158 Z" fill="none" stroke="#111" stroke-width="3" stroke-linejoin="round"/>

            {{-- Bras (pivot à l'épaule) --}}
            <g class="dancer-arm-l">
                <rect x="64" y="92" width="14" height="50" rx="7" fill="#FF7900" stroke="#111" stroke-width="3"/>
                <circle cx="71" cy="146" r="8" fill="#8D5524" stroke="#111" stroke-width="2.5"/>
            </g>
            <g class="dancer-arm-r">
                <rect x="122" y="92" width="14" height="50" rx="7" fill="#FF7900" stroke="#111" stroke-width="3"/>
                <circle cx="129" cy="146" r="8" fill="#8D5524" stroke="#111" stroke-width="2.5"/>
            </g>

            {{-- Cou et tête --}}
            <rect x="93" y="76" width="14" height="14" rx="3" fill="#8D5524"/>
            <g class="dancer-head">
                <circle cx="100" cy="56" r="26" fill="#8D5524" stroke="#111" stroke-width="3"/>
                <path d="M75 48 Q100 18 125 48 Z" fill="#FF7900" stroke="#111" stroke-width="3" stroke-linejoin="round"/>
                <path d="M119 46 h20 q5 0 2 5 h-24 z" fill="#111"/>
                <g class="dancer-eyes">
                    <circle cx="91" cy="58" r="3.2" fill="#111"/>
                    <circle cx="109" cy="58" r="3.2" fill="#111"/>
                </g>
                <circle cx="84" cy="67" r="4" fill="#FF7900" opacity=".45"/>
                <circle cx="116" cy="67" r="4" fill="#FF7900" opacity=".45"/>
                <path d="M88 68 Q100 82 112 68" fill="none" stroke="#111" stroke-width="4" stroke-linecap="round"/>
            </g>
        </g>
    </g>
</svg>
