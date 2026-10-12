<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\EventParticipation;
use App\Services\Pdf\AttendancePosterInvalidation;
use PHPUnit\Framework\TestCase;

final class AttendancePosterInvalidationTest extends TestCase
{
    private AttendancePosterInvalidation $invalidation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invalidation = new AttendancePosterInvalidation();
    }

    public function test_player_id_change_invalidates(): void
    {
        $participation = new EventParticipation([
            'player_user_id' => 1,
            'event_division_id' => 10,
            'is_collecting_points' => true,
        ]);

        $this->assertTrue($this->invalidation->shouldInvalidateForParticipationChange(
            $participation,
            ['player_user_id' => 2],
        ));
    }

    public function test_event_division_id_change_invalidates(): void
    {
        $participation = new EventParticipation([
            'player_user_id' => 1,
            'event_division_id' => 10,
            'is_collecting_points' => true,
        ]);

        $this->assertTrue($this->invalidation->shouldInvalidateForParticipationChange(
            $participation,
            ['event_division_id' => 11],
        ));
    }

    public function test_is_collecting_points_change_invalidates(): void
    {
        $participation = new EventParticipation([
            'player_user_id' => 1,
            'event_division_id' => 10,
            'is_collecting_points' => true,
        ]);

        $this->assertTrue($this->invalidation->shouldInvalidateForParticipationChange(
            $participation,
            ['is_collecting_points' => false],
        ));
    }

    public function test_sort_order_only_does_not_invalidate(): void
    {
        $participation = new EventParticipation([
            'player_user_id' => 1,
            'event_division_id' => 10,
            'is_collecting_points' => true,
            'sort_order' => 1,
        ]);

        $this->assertFalse($this->invalidation->shouldInvalidateForParticipationChange(
            $participation,
            ['sort_order' => 5],
        ));
    }
}
