<?php

namespace App\Livewire\Admin\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\LettersDisplay;
use App\Enums\ValidationMode;
use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Campaign;
use App\Models\QrCode;
use App\Support\WordNormalizer;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Création / modification d'une campagne (dates, mot mystère, règles du jeu).
 * Le mot mystère n'est jamais renvoyé au navigateur : le champ reste vide
 * et n'est pris en compte que s'il est saisi.
 */
#[Layout('components.layouts.admin')]
class Form extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public ?int $campaignId = null;

    public string $name = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $daily_open_time = '';

    public string $daily_close_time = '';

    public string $word = '';

    // QR codes générés automatiquement pour chaque lettre (création ou changement de mot)
    public int $qrPerLetter = 2;

    public string $validation_mode = 'per_participant';

    public string $letters_display = 'shuffled';

    public int $max_word_attempts = 5;

    // false : toutes les lettres trouvées = gagné (pas de saisie du mot)
    public bool $require_word_entry = false;

    public string $otp_required = 'inherit';

    public int $min_seconds_between_scans = 20;

    public int $min_age = 18;

    public string $rules_md = '';

    public string $regulation_md = '';

    public string $regulation_version = '1.0';

    public function mount(?Campaign $campaign = null): void
    {
        $this->authorizeAdmin('manage');
        $this->qrPerLetter = (int) config('game.qr.default_per_letter', 2);

        if (! $campaign?->exists) {
            $tz = config('game.display_timezone');
            $this->starts_at = now($tz)->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
            $this->ends_at = now($tz)->addDays(10)->setTime(20, 0)->format('Y-m-d\TH:i');

            return;
        }

        $tz = config('game.display_timezone');
        $this->campaignId = $campaign->id;
        $this->fill([
            'name' => $campaign->name,
            'starts_at' => $campaign->starts_at->setTimezone($tz)->format('Y-m-d\TH:i'),
            'ends_at' => $campaign->ends_at->setTimezone($tz)->format('Y-m-d\TH:i'),
            'daily_open_time' => substr((string) $campaign->daily_open_time, 0, 5),
            'daily_close_time' => substr((string) $campaign->daily_close_time, 0, 5),
            'validation_mode' => $campaign->validation_mode->value,
            'letters_display' => $campaign->letters_display->value,
            'max_word_attempts' => $campaign->max_word_attempts,
            'require_word_entry' => $campaign->require_word_entry,
            'otp_required' => $campaign->otp_required === null ? 'inherit' : ($campaign->otp_required ? '1' : '0'),
            'min_seconds_between_scans' => $campaign->min_seconds_between_scans,
            'min_age' => $campaign->min_age,
            'rules_md' => (string) $campaign->rules_md,
            'regulation_md' => (string) $campaign->regulation_md,
            'regulation_version' => $campaign->regulation_version,
        ]);
    }

    protected function rules(): array
    {
        $campaign = $this->campaign();

        return [
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'daily_open_time' => ['nullable', 'date_format:H:i', 'required_with:daily_close_time'],
            'daily_close_time' => ['nullable', 'date_format:H:i', 'required_with:daily_open_time', 'after:daily_open_time'],
            'word' => $campaign?->isWordLocked()
                ? ['prohibited']
                : [$campaign ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[\pL\pM\s\'-]+$/u'],
            'qrPerLetter' => ['required', 'integer', 'between:0,50'],
            'validation_mode' => ['required', Rule::enum(ValidationMode::class)],
            'letters_display' => ['required', Rule::enum(LettersDisplay::class)],
            'max_word_attempts' => ['required', 'integer', 'between:1,20'],
            'require_word_entry' => ['boolean'],
            'otp_required' => ['required', 'in:inherit,1,0'],
            'min_seconds_between_scans' => ['required', 'integer', 'between:0,3600'],
            'min_age' => ['required', 'integer', 'between:0,99'],
            'rules_md' => ['nullable', 'string', 'max:20000'],
            'regulation_md' => ['nullable', 'string', 'max:100000'],
            'regulation_version' => ['required', 'string', 'max:20'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom', 'starts_at' => 'début', 'ends_at' => 'fin',
            'daily_open_time' => 'ouverture', 'daily_close_time' => 'fermeture',
            'word' => 'mot mystère', 'qrPerLetter' => 'QR codes par lettre', 'max_word_attempts' => 'tentatives',
            'min_seconds_between_scans' => 'délai entre scans', 'regulation_version' => 'version du règlement',
        ];
    }

    public function save(): void
    {
        $this->authorizeAdmin('manage');
        $this->validate();

        $tz = config('game.display_timezone');
        $attributes = [
            'name' => $this->name,
            'starts_at' => Carbon::parse($this->starts_at, $tz)->utc(),
            'ends_at' => Carbon::parse($this->ends_at, $tz)->utc(),
            'daily_open_time' => $this->daily_open_time ?: null,
            'daily_close_time' => $this->daily_close_time ?: null,
            'validation_mode' => $this->validation_mode,
            'letters_display' => $this->letters_display,
            'max_word_attempts' => $this->max_word_attempts,
            'require_word_entry' => $this->require_word_entry,
            'otp_required' => $this->otp_required === 'inherit' ? null : $this->otp_required === '1',
            'min_seconds_between_scans' => $this->min_seconds_between_scans,
            'min_age' => $this->min_age,
            'rules_md' => $this->rules_md ?: null,
            'regulation_md' => $this->regulation_md ?: null,
            'regulation_version' => $this->regulation_version,
        ];

        $generated = 0;

        try {
            $campaign = DB::transaction(function () use ($attributes, &$generated) {
                if (! $campaign = $this->campaign()) {
                    $campaign = Campaign::createWithWord($attributes + [
                        'slug' => $this->uniqueSlug($this->name),
                        'status' => CampaignStatus::Draft,
                    ], $this->word);

                    $this->audit('campaign.created', $campaign);
                    $generated = $this->generateQrCodes($campaign);

                    return $campaign;
                }

                $campaign->fill($attributes);
                $changed = array_keys($campaign->getDirty());
                $campaign->save();

                if (filled($this->word)) {
                    $lettersChanged = WordNormalizer::normalize($this->word) !== WordNormalizer::normalize((string) $campaign->mystery_word);

                    // Nouvelles lettres : les anciens QR (jamais scannés, le mot n'étant pas verrouillé) sont remplacés
                    if ($lettersChanged) {
                        $removed = $campaign->qrCodes()->count();
                        $campaign->qrCodes()->delete();
                        $this->audit('qr.all_deleted', $campaign, ['count' => $removed, 'reason' => 'changement du mot']);
                    }

                    $campaign->applyMysteryWord($this->word);
                    // Le mot lui-même n'est jamais journalisé
                    $this->audit('campaign.word_changed', $campaign, ['length' => $campaign->word_length]);

                    if ($lettersChanged) {
                        $generated = $this->generateQrCodes($campaign);
                    }
                }

                $this->audit('campaign.updated', $campaign, ['fields' => $changed]);

                return $campaign;
            });
        } catch (DomainException $e) {
            $this->addError('word', $e->getMessage());

            return;
        }

        $this->word = '';
        $this->flashForRedirect($generated > 0
            ? "Campagne enregistrée : {$generated} QR codes générés. Renseignez leur emplacement puis téléchargez le PDF."
            : 'Campagne enregistrée.');
        $this->redirectRoute('admin.qr-codes', ['campaign' => $campaign->id]);
    }

    public function render()
    {
        $campaign = $this->campaign();

        // Aperçu en direct du mot saisi : lettres et nombre de QR qui seront générés
        $preview = WordNormalizer::display($this->word);

        return view('livewire.admin.campaigns.form', [
            'campaign' => $campaign,
            'otpDefault' => config('game.otp.enabled'),
            'preview' => $preview === '' ? [] : explode(' ', $preview),
            'previewLetters' => strlen(str_replace(' ', '', $preview)),
        ])->title($campaign ? 'Modifier la campagne' : 'Nouvelle campagne');
    }

    private function generateQrCodes(Campaign $campaign): int
    {
        if ($this->qrPerLetter < 1) {
            return 0;
        }

        $count = QrCode::generateForCampaign($campaign, $this->qrPerLetter);
        $this->audit('qr.batch_generated', $campaign, ['count' => $count]);

        return $count;
    }

    private function campaign(): ?Campaign
    {
        return $this->campaignId ? Campaign::query()->findOrFail($this->campaignId) : null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'campagne';
        $slug = $base;

        for ($i = 2; Campaign::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
