{{--
    Planches A4 pour l'impression (rendu par dompdf : CSS 2.1, tableaux plutôt que flex/grid).
    Chaque QR porte le nom et la couleur de son jeu : avec plusieurs jeux en ligne, les poseurs
    et les joueurs ne confondent pas le « A » d'ORANGE avec celui de SIAO.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; }
        table { width: 100%; border-collapse: separate; border-spacing: 4mm; }
        td.cell { width: 50%; height: 82mm; text-align: center; vertical-align: top; border: 1.2mm solid {{ $color }}; padding: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .band { background: {{ $color }}; color: {{ $textColor }}; font-size: 12pt; font-weight: bold; padding: 2mm 3mm; text-transform: uppercase; letter-spacing: .5pt; }
        .brand { font-size: 8pt; margin-top: 2mm; }
        img { width: 52mm; height: 52mm; margin-top: 1mm; }
        .hint { font-size: 8pt; margin-top: 1mm; }
        .label { font-size: 7pt; color: #666; margin-top: 1mm; }
    </style>
</head>
<body>
    @forelse ($pages as $page)
        <div class="page">
            <table>
                @foreach ($page->chunk(2) as $row)
                    <tr>
                        @foreach ($row as $item)
                            <td class="cell">
                                <div class="band">Jeu {{ $campaign->name }}</div>
                                <div class="brand">La chasse au trésor du SIAO · Orange Money</div>
                                <img src="{{ $item['image'] }}" alt="">
                                <div class="hint">Scannez-moi pour gagner une lettre !</div>
                                {{-- Emplacement en petit : aide à la pose, ne révèle pas la lettre --}}
                                <div class="label">{{ $item['label'] }}</div>
                            </td>
                        @endforeach
                        @if ($row->count() < 2) <td></td> @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <p>Aucun QR code actif.</p>
    @endforelse
</body>
</html>
