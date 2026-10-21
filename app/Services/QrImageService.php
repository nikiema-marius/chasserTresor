<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Génération des images de QR codes (PNG, SVG), du ZIP et du PDF d'impression.
 *
 * Sous le QR figure uniquement le code de saisie manuelle : ni la lettre
 * ni sa position ne sont imprimées (elles se liraient sans scanner).
 */
class QrImageService
{
    public function png(QrCode $qr, int $size = 600): string
    {
        return $this->builder($qr, new PngWriter, $size)->build()->getString();
    }

    public function svg(QrCode $qr, int $size = 600): string
    {
        return $this->builder($qr, new SvgWriter, $size)->build()->getString();
    }

    public function filename(QrCode $qr, string $extension): string
    {
        return 'QR-'.str_pad((string) $qr->id, 4, '0', STR_PAD_LEFT).'-'.Str::slug($qr->label).'.'.$extension;
    }

    /**
     * Archive ZIP de tous les QR actifs. Retourne le chemin du fichier temporaire.
     */
    public function zip(Campaign $campaign): string
    {
        $path = storage_path('app/private/tmp/qr-'.$campaign->id.'-'.Str::random(12).'.zip');
        @mkdir(dirname($path), 0750, true);

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l\'archive ZIP.');
        }

        foreach ($this->printable($campaign) as $qr) {
            $zip->addFromString($this->filename($qr, 'png'), $this->png($qr));
        }

        $zip->close();

        return $path;
    }

    /** PDF A4 : 6 QR par page, découpables, avec emplacement et code manuel. */
    public function pdf(Campaign $campaign): string
    {
        $items = $this->printable($campaign)->map(fn (QrCode $qr) => [
            'label' => $qr->label,
            'code' => $qr->formattedManualCode(),
            'image' => 'data:image/png;base64,'.base64_encode($this->png($qr, 420)),
        ]);

        return Pdf::loadView('admin.qr-print', [
            'campaign' => $campaign,
            'pages' => $items->chunk(6),
        ])->setPaper('a4')->output();
    }

    /** @return Collection<int, QrCode> */
    private function printable(Campaign $campaign): Collection
    {
        return $campaign->qrCodes()->active()->with('campaignLetter')->orderBy('campaign_letter_id')->orderBy('id')->get();
    }

    private function builder(QrCode $qr, $writer, int $size): Builder
    {
        return new Builder(
            writer: $writer,
            data: $qr->scanUrl(),
            // Correction haute : le QR reste lisible s'il est abîmé ou partiellement sali
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: (int) round($size / 20),
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
            labelText: (string) $qr->formattedManualCode(),
            labelTextColor: new Color(0, 0, 0),
        );
    }
}
