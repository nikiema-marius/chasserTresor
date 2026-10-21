<?php

namespace App\Livewire\Admin\QrCodes;

use App\Enums\QrStatus;
use App\Enums\ValidationMode;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Campaign;
use App\Models\CampaignLetter;
use App\Models\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gestion des QR codes d'une campagne : génération, emplacement physique,
 * activation / désactivation / révocation / régénération, téléchargements.
 */
#[Layout('components.layouts.admin')]
#[Title('QR codes')]
class Index extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public int $campaignId;

    // Génération en lot
    public int $perLetter = 2;

    public string $labelPrefix = 'Emplacement';

    // Ajout unitaire
    public ?int $newPosition = null;

    public string $newLabel = '';

    // Édition en ligne
    public ?int $editingId = null;

    public string $editingLabel = '';

    public string $revokeReason = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
        $this->perLetter = (int) config('game.qr.default_per_letter', 2);
    }

    public function generateBatch(): void
    {
        $this->authorizeAdmin('manage');
        $this->validate([
            'perLetter' => ['required', 'integer', 'between:1,50'],
            'labelPrefix' => ['required', 'string', 'max:80'],
        ], attributes: ['perLetter' => 'nombre par lettre', 'labelPrefix' => 'préfixe']);

        $campaign = $this->campaign();
        $created = QrCode::generateForCampaign($campaign, $this->perLetter, $this->labelPrefix);

        $this->audit('qr.batch_generated', $campaign, ['count' => $created]);
        $this->toast("{$created} QR codes générés. Renseignez leur emplacement physique.");
    }

    public function addOne(): void
    {
        $this->authorizeAdmin('manage');
        $campaign = $this->campaign();

        $this->validate([
            'newPosition' => ['required', 'integer', Rule::exists('campaign_letters', 'position')->where('campaign_id', $campaign->id)],
            'newLabel' => ['required', 'string', 'max:120'],
        ], attributes: ['newPosition' => 'position', 'newLabel' => 'emplacement']);

        $letter = CampaignLetter::query()->where('campaign_id', $campaign->id)->where('position', $this->newPosition)->firstOrFail();
        $qr = QrCode::createForLetter($letter, $this->newLabel);

        $this->audit('qr.created', $qr);
        $this->reset('newPosition', 'newLabel');
        $this->toast('QR code ajouté.');
    }

    public function startEdit(int $id): void
    {
        $qr = $this->qr($id);
        $this->editingId = $qr->id;
        $this->editingLabel = $qr->label;
    }

    public function saveLabel(): void
    {
        $this->authorizeAdmin('manage');
        $this->validate(['editingLabel' => ['required', 'string', 'max:120']], attributes: ['editingLabel' => 'emplacement']);

        $qr = $this->qr((int) $this->editingId);
        $qr->update(['label' => $this->editingLabel]);

        $this->audit('qr.label_changed', $qr, ['label' => $this->editingLabel]);
        $this->reset('editingId', 'editingLabel');
    }

    public function setMode(int $id, string $mode): void
    {
        $this->authorizeAdmin('manage');

        $qr = $this->qr($id);
        $qr->update(['validation_mode' => $mode === 'inherit' ? null : ValidationMode::from($mode)]);

        $this->audit('qr.mode_changed', $qr, ['mode' => $mode]);
    }

    public function disable(int $id): void
    {
        $this->changeStatus($id, QrStatus::Disabled);
    }

    public function enable(int $id): void
    {
        $this->changeStatus($id, QrStatus::Active);
    }

    public function revoke(int $id): void
    {
        $this->authorizeAdmin('manage');
        $this->validate(['revokeReason' => ['nullable', 'string', 'max:255']]);

        $qr = $this->qr($id);

        if ($qr->status === QrStatus::Revoked) {
            return;
        }

        $qr->update([
            'status' => QrStatus::Revoked,
            'revoked_at' => now(),
            'revoked_reason' => $this->revokeReason ?: null,
        ]);

        $this->audit('qr.revoked', $qr, ['reason' => $this->revokeReason]);
        $this->reset('revokeReason');
        $this->toast('QR code révoqué définitivement.');
    }

    /** Révoque le QR et en crée un nouveau (nouveau jeton) pour la même position et le même emplacement. */
    public function regenerate(int $id): void
    {
        $this->authorizeAdmin('manage');
        $old = $this->qr($id);

        $new = DB::transaction(function () use ($old) {
            $old->update(['status' => QrStatus::Revoked, 'revoked_at' => now(), 'revoked_reason' => 'Régénéré']);

            return QrCode::createForLetter($old->campaignLetter, $old->label, $old->validation_mode);
        });

        $this->audit('qr.regenerated', $new, ['replaces' => $old->id]);
        $this->toast("QR #{$old->id} remplacé par #{$new->id}. Imprimez le nouveau.");
    }

    public function render()
    {
        $campaign = $this->campaign();

        return view('livewire.admin.qr-codes.index', [
            'campaign' => $campaign,
            'letters' => $campaign->letters()->with(['qrCodes' => fn ($q) => $q->orderBy('id')])->get(),
        ]);
    }

    private function changeStatus(int $id, QrStatus $status): void
    {
        $this->authorizeAdmin('manage');
        $qr = $this->qr($id);

        // Un QR révoqué ne revient jamais
        if ($qr->status === QrStatus::Revoked) {
            $this->toast('Un QR révoqué ne peut pas être réactivé : régénérez-le.', 'error');

            return;
        }

        $qr->update(['status' => $status, 'disabled_at' => $status === QrStatus::Disabled ? now() : null]);
        $this->audit($status === QrStatus::Active ? 'qr.enabled' : 'qr.disabled', $qr);
    }

    private function campaign(): Campaign
    {
        return Campaign::query()->with('letters')->findOrFail($this->campaignId);
    }

    private function qr(int $id): QrCode
    {
        return QrCode::query()->where('campaign_id', $this->campaignId)->findOrFail($id);
    }
}
