<?php

namespace App\Livewire\Participant;

use App\Enums\ScanSource;
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
    public function scan(string $raw, ScanService $service): void
    {
        $this->present($service->scan($this->participant(), $raw, ScanSource::InApp));
    }

    /** Saisie manuelle du code imprimé sous le QR (caméra refusée). */
    public function submitManual(ScanService $service): void
    {
        $this->validate(
            ['manualCode' => ['required', 'string', 'max:20']],
            attributes: ['manualCode' => 'code'],
        );

        $this->present($service->scan($this->participant(), $this->manualCode, ScanSource::ManualCode));
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
        $this->dispatch('scan-result', success: $outcome->isSuccess());
    }

    private function participant(): Participant
    {
        return Auth::guard('participant')->user();
    }
}
