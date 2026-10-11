<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\User;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerLevelAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/manager/player-level-assessments';

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($this->manager);
    }

    public function test_index_is_paginated_and_filterable(): void
    {
        PlayerLevelAssessment::factory()->readyForReview()->create();
        PlayerLevelAssessment::factory()->rejected()->create();

        $this->getJson(self::BASE)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::BASE . '?filters[review_status]=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_loads_player_and_submission(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();

        $this->getJson(self::BASE . '/' . $assessment->id_player_level_assessment)
            ->assertOk()
            ->assertJsonPath('data.id_player_level_assessment', $assessment->id_player_level_assessment)
            ->assertJsonPath('data.player.id_user', $assessment->player_user_id)
            ->assertJsonPath(
                'data.submission.id_player_custom_field_submission',
                $assessment->player_custom_field_submission_id
            );
    }

    public function test_approve_assigns_a_level(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();

        $this->patchJson(self::BASE . '/' . $assessment->id_player_level_assessment . '/approve', [
            'level' => '3.50',
            'review_message' => 'Welcome',
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.player_level.level', '3.50')
            ->assertJsonPath('data.player_level.band_name', 'Division 3 Mid');

        $this->assertDatabaseHas('player_levels', [
            'player_user_id' => $assessment->player_user_id,
            'player_level_assessment_id' => $assessment->id_player_level_assessment,
        ]);
    }

    public function test_approve_validates_pending_values_with_the_approving_manager(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();
        $pending = $this->valueFor($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $rejected = $this->valueFor($assessment, PlayerCustomFieldValueReviewStatus::Rejected, 1_600_000_001);

        $this->patchJson(self::BASE . '/' . $assessment->id_player_level_assessment . '/approve', [
            'level' => '3.50',
        ])->assertOk();

        $pending->refresh();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $pending->review_status);
        $this->assertSame((int) $assessment->fresh()->reviewed_at, (int) $pending->reviewed_at);
        $this->assertSame((int) $this->manager->id_user, (int) $pending->reviewed_by_manager_user_id);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Rejected, $rejected->fresh()->review_status);
        $this->assertSame(1_600_000_001, (int) $rejected->fresh()->reviewed_at);
    }

    public function test_reject_rejects_pending_values_with_the_reviewing_manager(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();
        $pending = $this->valueFor($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $validated = $this->valueFor($assessment, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);

        $this->patchJson(self::BASE . '/' . $assessment->id_player_level_assessment . '/reject')->assertOk();

        $pending->refresh();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Rejected, $pending->review_status);
        $this->assertNotNull($pending->reviewed_at);
        $this->assertSame((int) $this->manager->id_user, (int) $pending->reviewed_by_manager_user_id);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $validated->fresh()->review_status);
        $this->assertSame(1_600_000_003, (int) $validated->fresh()->reviewed_at);
    }

    public function test_approve_rejects_bad_levels_and_reviewed_assessments(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();
        $url = self::BASE . '/' . $assessment->id_player_level_assessment . '/approve';

        $this->patchJson($url, [])->assertStatus(422);
        $this->patchJson($url, ['level' => '3.5'])->assertStatus(422);
        $this->patchJson($url, ['level' => '9.50'])->assertStatus(422);
        $this->assertSame(0, PlayerLevel::count());

        $this->patchJson($url, ['level' => '3.50'])->assertOk();
        $this->patchJson($url, ['level' => '3.50'])->assertStatus(422);
    }

    public function test_reject_keeps_existing_player_levels(): void
    {
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $assessment->player_user_id]);

        $this->patchJson(self::BASE . '/' . $assessment->id_player_level_assessment . '/reject', [
            'review_message' => 'Try again',
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'rejected')
            ->assertJsonPath('data.review_message', 'Try again');

        $this->assertSame(1, PlayerLevel::where('player_user_id', $assessment->player_user_id)->count());
    }

    public function test_managers_cannot_create_or_delete_assessments(): void
    {
        $assessment = PlayerLevelAssessment::factory()->create();

        $this->postJson(self::BASE, [])->assertStatus(405);
        $this->deleteJson(self::BASE . '/' . $assessment->id_player_level_assessment)->assertStatus(405);
    }

    private function valueFor(
        PlayerLevelAssessment $assessment,
        PlayerCustomFieldValueReviewStatus $status,
        ?int $reviewedAt = null,
    ): PlayerCustomFieldValue {
        return PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $assessment->player_user_id,
            'submission_id' => $assessment->player_custom_field_submission_id,
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_APP,
            'review_status' => $status,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
