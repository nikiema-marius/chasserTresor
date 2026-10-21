<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\QrCode;
use App\Services\AuditLogger;
use App\Services\QrImageService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Téléchargement des QR codes : image unitaire (PNG/SVG), ZIP, PDF d'impression.
 */
class QrImageController extends Controller
{
    public function __construct(
        private readonly QrImageService $images,
        private readonly AuditLogger $audit,
    ) {}

    public function show(QrCode $qr, string $format): Response
    {
        [$content, $mime] = match ($format) {
            'svg' => [$this->images->svg($qr), 'image/svg+xml'],
            default => [$this->images->png($qr), 'image/png'],
        };

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$this->images->filename($qr, $format).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function zip(Campaign $campaign): BinaryFileResponse
    {
        $this->audit->log('qr.exported', $campaign, ['format' => 'zip']);

        return response()
            ->download($this->images->zip($campaign), 'qr-codes-'.$campaign->slug.'.zip')
            ->deleteFileAfterSend();
    }

    public function pdf(Campaign $campaign): Response
    {
        $this->audit->log('qr.exported', $campaign, ['format' => 'pdf']);

        return response($this->images->pdf($campaign), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="qr-codes-'.$campaign->slug.'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
