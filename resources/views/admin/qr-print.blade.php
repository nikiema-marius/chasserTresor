{{-- Planches A4 pour l'impression (rendu par dompdf : CSS 2.1, tableaux plutôt que flex/grid) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        td { width: 50%; height: 85mm; text-align: center; vertical-align: middle; border: 1px dashed #bbb; padding: 4mm; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .brand { font-size: 10pt; font-weight: bold; margin-bottom: 2mm; }
        .brand span { color: #FF7900; }
        img { width: 55mm; height: 55mm; }
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
                            <td>
                                <div class="brand">La chasse au trésor du SIAO · <span>Orange Money</span></div>
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
