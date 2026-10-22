<?php

namespace Tests\Feature;

use App\Enums\ScanResult;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\ParticipantLetter;
use App\Models\QrCode;
use App\Models\QrExclusiveClaim;
use App\Services\QrTokenService;
use App\Support\WordNormalizer;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 1 : garanties apportées par le modèle de données.
 */
class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mot_genere_une_position_par_lettre_y_compris_les_doublons(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertSame(7, $campaign->word_length);
        $this->assertSame(
            [1 => 'A', 2 => 'R', 3 => 'T', 4 => 'I', 5 => 'S', 6 => 'A', 7 => 'N'],
            $campaign->letters->pluck('letter', 'position')->all(),
        );
    }

    public function test_le_mot_est_chiffre_en_base(): void
    {
        $campaign = Campaign::factory()->create();

        $raw = \DB::table('campaigns')->where('id', $campaign->id)->value('mystery_word');

        $this->assertNotSame('ARTISAN', $raw);
        $this->assertTrue($campaign->fresh()->checkMysteryWord(' artisan '));
    }

    public function test_normalisation_insensible_casse_espaces_accents(): void
    {
        $this->assertSame('ARTISAN', WordNormalizer::normalize('  ar tì-san '));
        $this->assertTrue(WordNormalizer::equals('ARTISAN', 'Artisan'));
        $this->assertFalse(WordNormalizer::equals('ARTISAN', 'ARTISANS'));
        $this->assertFalse(WordNormalizer::equals('ARTISAN', ''));
    }

    public function test_le_mot_verrouille_ne_peut_plus_changer(): void
    {
        $campaign = Campaign::factory()->create();
        $campaign->forceFill(['word_locked_at' => now()])->save();

        $this->expectException(DomainException::class);
        $campaign->applyMysteryWord('BOGOLAN');
    }

    public function test_un_telephone_ne_peut_s_inscrire_qu_une_fois_par_campagne(): void
    {
        $campaign = Campaign::factory()->create();
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670000001']);

        $this->expectException(UniqueConstraintViolationException::class);
        Participant::factory()->for($campaign)->create(['phone_e164' => '+22670000001']);
    }

    public function test_une_position_ne_peut_etre_obtenue_qu_une_fois(): void
    {
        $campaign = Campaign::factory()->create();
        $letter = $campaign->letters->first();
        [$qr1, $qr2] = QrCode::factory()->count(2)->forLetter($letter)->create();
        $participant = Participant::factory()->for($campaign)->create();

        ParticipantLetter::create([
            'participant_id' => $participant->id, 'campaign_letter_id' => $letter->id,
            'qr_code_id' => $qr1->id, 'collected_at' => now(),
        ]);

        // Un second QR de la même position ne donne pas une seconde lettre
        $this->expectException(UniqueConstraintViolationException::class);
        ParticipantLetter::create([
            'participant_id' => $participant->id, 'campaign_letter_id' => $letter->id,
            'qr_code_id' => $qr2->id, 'collected_at' => now(),
        ]);
    }

    public function test_un_qr_exclusif_ne_peut_etre_reclame_qu_une_fois(): void
    {
        $qr = QrCode::factory()->create();
        [$a, $b] = Participant::factory()->count(2)->for($qr->campaign)->create();

        QrExclusiveClaim::create(['qr_code_id' => $qr->id, 'participant_id' => $a->id, 'claimed_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        QrExclusiveClaim::create(['qr_code_id' => $qr->id, 'participant_id' => $b->id, 'claimed_at' => now()]);
    }

    public function test_plusieurs_campagnes_en_ligne_en_meme_temps(): void
    {
        $orange = Campaign::factory()->withWord('ORANGE')->create();
        $siao = Campaign::factory()->withWord('SIAO')->create();
        $soon = Campaign::factory()->notStarted()->create();            // active, commence plus tard : en ligne
        $closed = Campaign::factory()->closed()->create();
        $draft = Campaign::factory()->create(['status' => 'draft']);
        $ended = Campaign::factory()->create(['ends_at' => now()->subMinute()]);

        $online = Campaign::online()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$orange->id, $siao->id, $soon->id], $online);
        $this->assertTrue($orange->isOnline());
        $this->assertFalse($closed->isOnline());
        $this->assertFalse($draft->isOnline());
        $this->assertFalse($ended->isOnline());
    }

    public function test_disponibilite_de_la_campagne(): void
    {
        $this->assertNull(Campaign::factory()->create()->availabilityAt(now()));
        $this->assertSame(ScanResult::CampaignClosed, Campaign::factory()->closed()->create()->availabilityAt(now()));
        $this->assertSame(ScanResult::CampaignNotStarted, Campaign::factory()->notStarted()->create()->availabilityAt(now()));

        $hours = Campaign::factory()->create(['daily_open_time' => '09:00:00', 'daily_close_time' => '20:00:00']);
        $this->assertSame(ScanResult::OutsideHours, $hours->availabilityAt(now()->setTime(21, 0)));
        $this->assertNull($hours->availabilityAt(now()->setTime(10, 0)));
    }

    public function test_jetons_aleatoires_sans_information_sur_la_lettre(): void
    {
        $qr = QrCode::factory()->create();
        $tokens = app(QrTokenService::class);

        $this->assertSame(32, strlen($qr->plainToken()));
        $this->assertTrue($qr->is(QrCode::findByToken($qr->plainToken())));
        $this->assertTrue($qr->is(QrCode::findByManualCode(strtolower($qr->formattedManualCode()))));
        $this->assertNull(QrCode::findByToken('invalide'));
        $this->assertSame($qr->plainToken(), $tokens->extractToken($qr->scanUrl()));
        $this->assertStringNotContainsString((string) $qr->campaignLetter->letter, parse_url($qr->scanUrl(), PHP_URL_QUERY) ?? '');
        $this->assertArrayNotHasKey('token_encrypted', $qr->toArray());
    }
}
