<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\PadelEvent;
use App\Services\Pdf\EventPosterInvalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventPosterInvalidationGuardTest extends TestCase
{
    use RefreshDatabase;

    private EventPosterInvalidationGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new EventPosterInvalidationGuard();
    }

    public function test_last_event_id_null_when_no_events(): void
    {
        $this->assertNull($this->guard->lastEditionId());
        $this->assertFalse($this->guard->shouldInvalidateEditionId(1));
    }

    public function test_last_event_id_single_edition(): void
    {
        $event = PadelEvent::factory()->create(['date' => '2026-06-01']);

        $this->assertSame((int) $event->id_event, $this->guard->lastEditionId());
        $this->assertTrue($this->guard->shouldInvalidateEditionId((int) $event->id_event));
    }

    public function test_last_event_id_tie_breaks_by_id_on_same_date(): void
    {
        $older = PadelEvent::factory()->create(['date' => '2026-06-15']);
        $newer = PadelEvent::factory()->create(['date' => '2026-06-15']);

        $lastId = (int) max($older->id_event, $newer->id_event);
        $otherId = (int) min($older->id_event, $newer->id_event);

        $this->assertSame($lastId, $this->guard->lastEditionId());
        $this->assertTrue($this->guard->shouldInvalidateEditionId($lastId));
        $this->assertFalse($this->guard->shouldInvalidateEditionId($otherId));
    }

    public function test_finalized_edition_is_never_invalidated(): void
    {
        $event = PadelEvent::factory()->create([
            'date' => '2026-12-01',
            'finalized_at' => time(),
        ]);

        $this->assertFalse($this->guard->shouldInvalidateEditionId((int) $event->id_event));
    }

    public function test_not_last_edition_is_not_invalidated(): void
    {
        $older = PadelEvent::factory()->create(['date' => '2026-05-01']);
        PadelEvent::factory()->create(['date' => '2026-06-01']);

        $this->assertFalse($this->guard->shouldInvalidateEditionId((int) $older->id_event));
    }

    public function test_last_non_finalized_edition_is_invalidated(): void
    {
        PadelEvent::factory()->create(['date' => '2026-05-01']);
        $last = PadelEvent::factory()->create(['date' => '2026-06-01', 'finalized_at' => null]);

        $this->assertTrue($this->guard->shouldInvalidateEditionId((int) $last->id_event));
    }

    public function test_unknown_event_id_returns_false(): void
    {
        PadelEvent::factory()->create(['date' => '2026-06-01']);

        $this->assertFalse($this->guard->shouldInvalidateEditionId(999_999));
    }
}
