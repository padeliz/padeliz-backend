<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerLevel;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\Player;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use App\Services\PlayerLevel\LevelAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LevelAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private LevelAssessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LevelAssessmentService::class);
    }

    public function test_ensure_pending_reuses_existing_pending_row(): void
    {
        $player = Player::factory()->create();
        $first = $this->service->ensurePending($player);
        $second = $this->service->ensurePending($player);

        $this->assertSame($first->id_player_level_assessment, $second->id_player_level_assessment);
        $this->assertSame(1, PlayerLevelAssessment::query()->where('player_id', $player->id_player)->count());
    }

    public function test_approve_upserts_player_level_and_reject_keeps_it(): void
    {
        $player = Player::factory()->create();
        $assessment = $this->service->ensurePending($player);

        $this->service->approve($assessment, '3.50');
        $assessment->refresh();

        $this->assertSame(AssessmentReviewStatus::Approved, $assessment->review_status);
        $level = PlayerLevel::query()
            ->where('player_level_assessment_id', $assessment->id_player_level_assessment)
            ->firstOrFail();
        $this->assertSame('3.50', (string) $level->level);

        $pending = $this->service->ensurePending($player);
        $this->service->reject($pending, 'needs more detail');

        $this->assertTrue(
            PlayerLevel::query()
                ->where('player_level_assessment_id', $assessment->id_player_level_assessment)
                ->where('level', '3.50')
                ->exists()
        );
    }

    public function test_mark_ready_locks_and_reopen_unlocks(): void
    {
        $player = Player::factory()->create();
        $assessment = $this->service->ensurePending($player);

        $this->service->markReady($assessment);
        $assessment->refresh();
        $this->assertTrue($assessment->ready_for_review);
        $this->assertNotNull($assessment->submitted_at);

        $this->service->reopenPending($assessment);
        $assessment->refresh();
        $this->assertFalse($assessment->ready_for_review);
    }

    public function test_cannot_approve_a_non_pending_assessment(): void
    {
        $player = Player::factory()->create();
        $assessment = $this->service->ensurePending($player);
        $this->service->reject($assessment);

        $this->expectException(ValidationException::class);
        $this->service->approve($assessment->fresh(), '3.50');
    }

    public function test_approve_flips_only_pending_values_to_validated_with_the_manager(): void
    {
        $player = Player::factory()->create();
        $manager = Player::factory()->manager()->create();
        $assessment = $this->service->ensurePending($player);
        $other = $this->service->ensurePending(Player::factory()->create());

        $pending = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $rejected = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Rejected, 1_600_000_001);
        $discarded = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Discarded, 1_600_000_002);
        $validated = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003, $manager);
        $untouched = $this->valueIn($other, PlayerCustomFieldValueReviewStatus::Pending);

        $this->service->approve($assessment, '3.50', null, (int) $manager->id_player);

        $flipped = $pending->fresh();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $flipped->review_status);
        $this->assertSame((int) $assessment->fresh()->reviewed_at, (int) $flipped->reviewed_at);
        $this->assertSame((int) $manager->id_player, (int) $flipped->reviewed_by_manager_id);

        $this->assertTerminalUnchanged($rejected, PlayerCustomFieldValueReviewStatus::Rejected, 1_600_000_001);
        $this->assertTerminalUnchanged($discarded, PlayerCustomFieldValueReviewStatus::Discarded, 1_600_000_002);
        $this->assertTerminalUnchanged($validated, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Pending, $untouched->fresh()->review_status);
    }

    public function test_reject_flips_only_pending_values_to_rejected_with_the_manager(): void
    {
        $manager = Player::factory()->manager()->create();
        $assessment = $this->service->ensurePending(Player::factory()->create());

        $pending = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $validated = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);
        $discarded = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Discarded, 1_600_000_002);

        $this->service->reject($assessment, 'no', (int) $manager->id_player);

        $flipped = $pending->fresh();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Rejected, $flipped->review_status);
        $this->assertNotNull($flipped->reviewed_at);
        $this->assertSame((int) $manager->id_player, (int) $flipped->reviewed_by_manager_id);

        $this->assertTerminalUnchanged($validated, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);
        $this->assertTerminalUnchanged($discarded, PlayerCustomFieldValueReviewStatus::Discarded, 1_600_000_002);
    }

    public function test_discard_flips_only_pending_values_to_discarded_without_a_manager(): void
    {
        $assessment = $this->service->ensurePending(Player::factory()->create());

        $pending = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $rejected = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Rejected, 1_600_000_001);
        $validated = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);

        $this->service->discardPending($assessment);

        $flipped = $pending->fresh();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Discarded, $flipped->review_status);
        $this->assertNotNull($flipped->reviewed_at);
        $this->assertNull($flipped->reviewed_by_manager_id);

        $this->assertTerminalUnchanged($rejected, PlayerCustomFieldValueReviewStatus::Rejected, 1_600_000_001);
        $this->assertTerminalUnchanged($validated, PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_003);
    }

    public function test_deleting_an_assessment_removes_its_submission_values_then_the_submission(): void
    {
        $assessment = $this->service->ensurePending(Player::factory()->create());
        $other = $this->service->ensurePending(Player::factory()->create());

        $value = $this->valueIn($assessment, PlayerCustomFieldValueReviewStatus::Pending);
        $kept = $this->valueIn($other, PlayerCustomFieldValueReviewStatus::Pending);
        $submissionId = $assessment->player_custom_field_submission_id;

        $assessment->delete();

        $this->assertNull(PlayerLevelAssessment::query()->find($assessment->getKey()));
        $this->assertNull(PlayerCustomFieldValue::query()->find($value->getKey()));
        $this->assertNull(PlayerCustomFieldSubmission::query()->find($submissionId));
        $this->assertNotNull(PlayerCustomFieldValue::query()->find($kept->getKey()));
        $this->assertNotNull(PlayerCustomFieldSubmission::query()->find($other->player_custom_field_submission_id));
    }

    private function valueIn(
        PlayerLevelAssessment $assessment,
        PlayerCustomFieldValueReviewStatus $status,
        ?int $reviewedAt = null,
        ?Player $manager = null,
    ): PlayerCustomFieldValue {
        return PlayerCustomFieldValue::factory()->create([
            'player_id' => $assessment->player_id,
            'submission_id' => $assessment->player_custom_field_submission_id,
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_APP,
            'review_status' => $status,
            'reviewed_at' => $reviewedAt,
            'reviewed_by_manager_id' => $manager?->id_player,
        ]);
    }

    private function assertTerminalUnchanged(
        PlayerCustomFieldValue $value,
        PlayerCustomFieldValueReviewStatus $status,
        int $reviewedAt,
    ): void {
        $fresh = $value->fresh();

        $this->assertSame($status, $fresh->review_status);
        $this->assertSame($reviewedAt, (int) $fresh->reviewed_at);
        $this->assertSame($value->reviewed_by_manager_id, $fresh->reviewed_by_manager_id);
    }
}
