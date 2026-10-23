<?php

namespace App\Livewire\Participant;

use App\Enums\ScanResult;
use App\Enums\ScanSource;
use App\Models\QrCode;
use App\Services\QrTokenService;
use App\Models\Participant;
use App\Services\ScanOutcome;
use App\Services\ScanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page « Scanner » : la caméra est gérée côté navigateur (qr-scanner),
 * la validation est faite exclusivement côté serveur via ScanService.
 */
#[Layout('components.layouts.app', ['immersive' => true])]
#[Title('Scanner un QR code')]
class Scanner extends Component
{
    // Résultat du dernier scan (affichage uniquement, recalculé côté serveur)
    #[Locked]
    public ?array $outcome = null;

    public string $manualCode = '';

    /** Appelé par le JavaScript avec le contenu brut du QR lu. */
    public function scan(string $raw, ScanService $service, QrTokenService $tokens): void
    {
        $outcome = $service->scan($this->participant(), $raw, ScanSource::InApp);

        // QR d'un autre jeu en ligne : le lien de scan bascule sur ce jeu (ou propose de le rejoindre)
        if ($outcome->result === ScanResult::OtherCampaign && ($token = $tokens->extractToken($raw))) {
            $this->redirectRoute('scan.link', $token);

            return;
        }

        $this->present($outcome);
    }

    /** Saisie manuelle du code imprimé sous le QR (caméra refusée). */
    public function submitManual(ScanService $service): void
    {
        $this->validate(
            ['manualCode' => ['required', 'string', 'max:20']],
            attributes: ['manualCode' => 'code'],
        );

        $outcome = $service->scan($this->participant(), $this->manualCode, ScanSource::ManualCode);

        if ($outcome->result === ScanResult::OtherCampaign && ($qr = QrCode::findByManualCode($this->manualCode))) {
            $this->redirectRoute('scan.link', $qr->plainToken());

            return;
        }

        $this->present($outcome);
        $this->manualCode = '';
    }

    /** Ferme l'écran de résultat pour scanner à nouveau. */
    public function dismiss(): void
    {
        $this->outcome = null;
    }

    public function render()
    {
        return view('livewire.participant.scanner', [
            'participant' => $this->participant()->refresh()->load('campaign'),
        ]);
    }

    private function present(ScanOutcome $outcome): void
    {
        $this->outcome = $outcome->toFlash();
        $this->dispatch('scan-result', success: $outcome->isSuccess(), solved: $outcome->solved);
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
