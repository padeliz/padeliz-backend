<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\Edition;
use App\Services\Pdf\EditionPosterInvalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EditionPosterInvalidationGuardTest extends TestCase
{
    use RefreshDatabase;

    private EditionPosterInvalidationGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new EditionPosterInvalidationGuard();
    }

    public function test_last_edition_id_null_when_no_editions(): void
    {
        $this->assertNull($this->guard->lastEditionId());
        $this->assertFalse($this->guard->shouldInvalidateEditionId(1));
    }

    public function test_last_edition_id_single_edition(): void
    {
        $edition = Edition::factory()->create(['date' => '2026-06-01']);

        $this->assertSame((int) $edition->id_edition, $this->guard->lastEditionId());
        $this->assertTrue($this->guard->shouldInvalidateEditionId((int) $edition->id_edition));
    }

    public function test_last_edition_id_tie_breaks_by_id_on_same_date(): void
    {
        $older = Edition::factory()->create(['date' => '2026-06-15']);
        $newer = Edition::factory()->create(['date' => '2026-06-15']);

        $lastId = (int) max($older->id_edition, $newer->id_edition);
        $otherId = (int) min($older->id_edition, $newer->id_edition);

        $this->assertSame($lastId, $this->guard->lastEditionId());
        $this->assertTrue($this->guard->shouldInvalidateEditionId($lastId));
        $this->assertFalse($this->guard->shouldInvalidateEditionId($otherId));
    }

    public function test_finalized_edition_is_never_invalidated(): void
    {
        $edition = Edition::factory()->create([
            'date' => '2026-12-01',
            'finalized_at' => time(),
        ]);

        $this->assertFalse($this->guard->shouldInvalidateEditionId((int) $edition->id_edition));
    }

    public function test_not_last_edition_is_not_invalidated(): void
    {
        $older = Edition::factory()->create(['date' => '2026-05-01']);
        Edition::factory()->create(['date' => '2026-06-01']);

        $this->assertFalse($this->guard->shouldInvalidateEditionId((int) $older->id_edition));
    }

    public function test_last_non_finalized_edition_is_invalidated(): void
    {
        Edition::factory()->create(['date' => '2026-05-01']);
        $last = Edition::factory()->create(['date' => '2026-06-01', 'finalized_at' => null]);

        $this->assertTrue($this->guard->shouldInvalidateEditionId((int) $last->id_edition));
    }

    public function test_unknown_edition_id_returns_false(): void
    {
        Edition::factory()->create(['date' => '2026-06-01']);

        $this->assertFalse($this->guard->shouldInvalidateEditionId(999_999));
    }
}
