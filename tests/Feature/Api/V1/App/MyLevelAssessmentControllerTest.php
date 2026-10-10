<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use App\Models\PlayerProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class MyLevelAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private const BASE = '/api/v1/app/my/level-assessment';

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->player = Player::factory()->create();
        Sanctum::actingAs($this->player);
    }

    public function test_status_reports_latest_and_counts(): void
    {
        PlayerLevelAssessment::factory()->forPlayer($this->player)->rejected()->create();
        PlayerLevelAssessment::factory()->forPlayer($this->player)->create();

        $this->getJson(self::BASE . '/status')
            ->assertOk()
            ->assertJsonPath('data.latest', 'pending')
            ->assertJsonPath('data.overall.pending', 1)
            ->assertJsonPath('data.overall.rejected', 1)
            ->assertJsonPath('data.overall.approved', 0);
    }

    public function test_latest_and_pending_return_404_when_empty(): void
    {
        $this->getJson(self::BASE . '/latest')->assertNotFound();
        $this->getJson(self::BASE . '/pending')->assertNotFound();
    }

    public function test_post_pending_creates_then_returns_existing(): void
    {
        $this->postJson(self::BASE . '/pending')
            ->assertCreated()
            ->assertJsonPath('data.review_status', 'pending')
            ->assertJsonPath('data.ready_for_review', false);

        $this->postJson(self::BASE . '/pending')->assertOk();

        $this->assertSame(1, PlayerLevelAssessment::where('player_id', $this->player->id_player)->count());
        $this->getJson(self::BASE . '/pending')->assertOk();
        $this->getJson(self::BASE . '/latest')->assertOk();
    }

    public function test_custom_fields_are_written_into_the_assessment_submission(): void
    {
        $field = PlayerCustomField::factory()->create(['code' => 'year_of_birth_text']);

        $created = $this->postJson(self::BASE . '/pending', [
            'custom_fields' => [$field->code => 'first answer'],
        ])->assertCreated();

        $submissionId = $created->json('data.player_custom_field_submission_id');

        $this->patchJson(self::BASE . '/pending', [
            'custom_fields' => [$field->code => 'second answer'],
        ])->assertOk()
            ->assertJsonPath('data.player_custom_field_submission_id', $submissionId)
            ->assertJsonCount(1, 'data.submission.values')
            ->assertJsonPath('data.submission.values.0.value_text', 'second answer');
    }

    public function test_unknown_custom_field_code_is_rejected(): void
    {
        $this->patchJson(self::BASE . '/pending', [
            'custom_fields' => ['does_not_exist' => 'x'],
        ])->assertStatus(422);
    }

    public function test_mutations_are_locked_while_ready_for_review_until_reopened(): void
    {
        $field = PlayerCustomField::factory()->create();
        $this->postJson(self::BASE . '/pending')->assertCreated();

        $this->postJson(self::BASE . '/pending/ready')
            ->assertOk()
            ->assertJsonPath('data.ready_for_review', true);

        $this->patchJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'x']])
            ->assertStatus(422);
        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'x']])
            ->assertStatus(422);
        $this->postJson(self::BASE . '/pending')
            ->assertStatus(422)
            ->assertJsonValidationErrors('ready_for_review');
        $this->postJson(self::BASE . '/pending', ['custom_fields' => []])
            ->assertStatus(422);
        $this->post(
            self::BASE . '/pending/profile-image',
            ['image' => $this->createFakeImage('face.png')],
            ['Accept' => 'application/json']
        )->assertStatus(422);

        $this->postJson(self::BASE . '/pending/reopen')
            ->assertOk()
            ->assertJsonPath('data.ready_for_review', false);

        $this->patchJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'x']])->assertOk();
        $this->postJson(self::BASE . '/pending')->assertOk();
    }

    public function test_locked_assessment_keeps_its_values_untouched(): void
    {
        $field = PlayerCustomField::factory()->create();
        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'before']])->assertCreated();
        $this->postJson(self::BASE . '/pending/ready')->assertOk();

        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'after']])->assertStatus(422);

        $this->assertSame('before', PlayerCustomFieldValue::query()->firstOrFail()->value_text);
    }

    public function test_app_answers_are_pending_and_empty_answers_write_no_row(): void
    {
        $filled = PlayerCustomField::factory()->create();
        $empty = PlayerCustomField::factory()->create();

        $this->postJson(self::BASE . '/pending', [
            'custom_fields' => [$filled->code => 'answer', $empty->code => ''],
        ])->assertCreated();

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $value = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Pending, $value->review_status);
        $this->assertNull($value->reviewed_at);
        $this->assertNull($value->reviewed_by_manager_id);

        $this->patchJson(self::BASE . '/pending', ['custom_fields' => [$filled->code => '']])->assertOk();
        $this->assertSame('answer', $value->fresh()->value_text);
        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
    }

    public function test_editing_the_draft_updates_the_same_row_and_a_new_assessment_adds_a_pending_row(): void
    {
        $field = PlayerCustomField::factory()->create();
        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'v1']])->assertCreated();
        $first = PlayerCustomFieldValue::query()->firstOrFail();

        $this->patchJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'v2']])->assertOk();
        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertSame($first->getKey(), PlayerCustomFieldValue::query()->firstOrFail()->getKey());

        $first->update([
            'review_status' => PlayerCustomFieldValueReviewStatus::Validated,
            'reviewed_at' => time(),
        ]);
        PlayerLevelAssessment::query()->update(['review_status' => 'approved', 'reviewed_at' => time()]);

        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'v2']])->assertCreated();

        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
        $newest = PlayerCustomFieldValue::query()->orderByDesc('id_player_custom_field_value')->firstOrFail();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Pending, $newest->review_status);
        $this->assertSame('v2', $newest->value_text);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $first->fresh()->review_status);
    }

    public function test_discarding_the_assessment_discards_its_pending_values(): void
    {
        $field = PlayerCustomField::factory()->create();
        $this->postJson(self::BASE . '/pending', ['custom_fields' => [$field->code => 'draft']])->assertCreated();

        $this->postJson(self::BASE . '/discard')->assertOk();

        $value = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Discarded, $value->review_status);
        $this->assertNotNull($value->reviewed_at);
        $this->assertNull($value->reviewed_by_manager_id);
    }

    public function test_discard_closes_the_pending_assessment(): void
    {
        $this->postJson(self::BASE . '/discard')->assertOk();

        $this->postJson(self::BASE . '/pending')->assertCreated();
        $this->postJson(self::BASE . '/discard')->assertOk();

        $this->getJson(self::BASE . '/pending')->assertNotFound();
        $this->getJson(self::BASE . '/status')->assertJsonPath('data.latest', 'discarded');
    }

    public function test_current_level_returns_level_with_band_name(): void
    {
        $this->getJson(self::BASE . '/current-level')->assertNotFound();

        PlayerLevel::factory()->level('3.50')->create(['player_id' => $this->player->id_player]);

        $this->getJson(self::BASE . '/current-level')
            ->assertOk()
            ->assertJsonPath('data.level', '3.50')
            ->assertJsonPath('data.band_name', 'Division 3 Mid');
    }

    public function test_profile_image_upload_is_linked_to_the_pending_assessment(): void
    {
        $response = $this->post(
            self::BASE . '/pending/profile-image',
            ['image' => $this->createFakeImage('face.png')],
            ['Accept' => 'application/json']
        );
        $response->assertCreated();

        $assessmentId = $response->json('data.id_player_level_assessment');
        $image = PlayerProfileImage::where('player_id', $this->player->id_player)->firstOrFail();

        $this->assertSame(PlayerProfileImage::UPLOADED_VIA_APP_LEVEL_ASSESSMENT, $image->uploaded_via);
        $this->assertSame($assessmentId, $image->uploaded_via_level_assessment_id);
        $response->assertJsonCount(1, 'data.profile_images');

        $imageUrl = $response->json('data.profile_images.0.url');
        $this->assertIsString($imageUrl);
        $this->assertMatchesRegularExpression('#^https?://#i', $imageUrl);

        $this->deleteJson(self::BASE . '/pending/profile-image/' . $image->id)->assertOk();
        $this->assertSame(0, PlayerProfileImage::where('player_id', $this->player->id_player)->count());
    }

    public function test_assessment_image_upload_ignores_other_sources_until_assessment_cap(): void
    {
        for ($i = 0; $i < 5; $i++) {
            PlayerProfileImage::factory()->create([
                'player_id' => $this->player->id_player,
                'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_APP,
                'uploaded_via_level_assessment_id' => null,
            ]);
        }

        $this->post(
            self::BASE . '/pending/profile-image',
            ['image' => $this->createFakeImage('a1.png')],
            ['Accept' => 'application/json']
        )->assertCreated();

        for ($i = 0; $i < PlayerProfileImage::MAX_PER_PLAYER_ASSESSMENT - 1; $i++) {
            $this->post(
                self::BASE . '/pending/profile-image',
                ['image' => $this->createFakeImage("a{$i}.png")],
                ['Accept' => 'application/json']
            )->assertCreated();
        }

        $blocked = $this->post(
            self::BASE . '/pending/profile-image',
            ['image' => $this->createFakeImage('blocked.png')],
            ['Accept' => 'application/json']
        );
        $blocked->assertStatus(422);
        $this->assertStringContainsString(
            (string) PlayerProfileImage::MAX_PER_PLAYER_ASSESSMENT,
            (string) $blocked->json('message')
        );
    }

    public function test_discard_deletes_assessment_linked_images_only(): void
    {
        $kept = PlayerProfileImage::factory()->create([
            'player_id' => $this->player->id_player,
            'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_APP,
            'uploaded_via_level_assessment_id' => null,
        ]);

        $upload = $this->post(
            self::BASE . '/pending/profile-image',
            ['image' => $this->createFakeImage('discard-me.png')],
            ['Accept' => 'application/json']
        )->assertCreated();

        $assessmentImageId = $upload->json('data.profile_images.0.id');
        $this->assertNotNull($assessmentImageId);

        $this->postJson(self::BASE . '/discard')->assertOk();

        $this->assertDatabaseHas('player_profile_images', ['id' => $kept->id]);
        $this->assertDatabaseMissing('player_profile_images', ['id' => $assessmentImageId]);
    }

    public function test_playtomic_and_rankedin_urls_are_stored_as_ids(): void
    {
        $this->assertNotNull(PlayerCustomField::query()->where('code', 'playtomic_id')->first());
        $this->assertNotNull(PlayerCustomField::query()->where('code', 'rankedin_id')->first());

        $response = $this->postJson(self::BASE . '/pending', [
            'custom_fields' => [
                'playtomic_id' => 'https://playtomic.com/profile/user/424242',
                'rankedin_id' => 'https://rankedin.com/player/R-xyz/',
            ],
        ])->assertCreated();

        $values = collect($response->json('data.submission.values'));
        $playtomic = $values->first(fn (array $v) => ($v['field']['code'] ?? null) === 'playtomic_id');
        $rankedin = $values->first(fn (array $v) => ($v['field']['code'] ?? null) === 'rankedin_id');
        $this->assertSame('424242', $playtomic['value_text'] ?? $playtomic['value'] ?? null);
        $this->assertSame('R-xyz', $rankedin['value_text'] ?? $rankedin['value'] ?? null);

        $this->patchJson(self::BASE . '/pending', [
            'custom_fields' => [
                'playtomic_id' => '424242',
                'rankedin_id' => 'R-xyz',
            ],
        ])->assertOk();
    }

    public function test_unparseable_playtomic_or_rankedin_url_returns_422(): void
    {
        $this->assertNotNull(PlayerCustomField::query()->where('code', 'playtomic_id')->first());
        $this->assertNotNull(PlayerCustomField::query()->where('code', 'rankedin_id')->first());

        $this->postJson(self::BASE . '/pending', [
            'custom_fields' => ['playtomic_id' => 'https://example.test/nope'],
        ])->assertStatus(422);

        $this->postJson(self::BASE . '/pending', [
            'custom_fields' => ['rankedin_id' => 'https://rankedin.com/players/missing'],
        ])->assertStatus(422);
    }

    public function test_big_tournament_division_stores_level_id_and_name(): void
    {
        $level = \App\Models\Level::query()->orderBy('id_level')->firstOrFail();

        $response = $this->postJson(self::BASE . '/pending', [
            'custom_fields' => [
                'big_tournament_division' => (int) $level->id_level,
            ],
        ])->assertCreated();

        $values = collect($response->json('data.submission.values'));
        $row = $values->first(
            fn (array $v) => ($v['field']['code'] ?? null) === 'big_tournament_division'
        );

        $this->assertNotNull($row);
        $this->assertSame((int) $level->id_level, (int) ($row['value_integer'] ?? 0));
        $this->assertSame((string) $level->name, $row['value_text'] ?? null);
        $this->assertSame((string) $level->name, $row['value'] ?? null);
    }
}
