<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Traits\HasAdvancedPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class HasAdvancedPaginationTest extends TestCase
{
    use RefreshDatabase;

    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class () {
            use HasAdvancedPagination;

            public function paginate(Request $request, ?callable $extendSearch = null): Builder
            {
                $query = Player::query();

                return $this->applyAdvancedPagination(
                    $query,
                    $request,
                    ['first_name'],
                    [],
                    [],
                    $extendSearch
                );
            }
        };
    }

    public function test_extend_search_runs_inside_same_or_group(): void
    {
        Player::factory()->create(['first_name' => 'Alice', 'last_name' => 'One']);
        $match = Player::factory()->create(['first_name' => 'Bob', 'last_name' => 'Two']);
        PlayerEmail::factory()->create([
            'player_id' => $match->id_player,
            'email' => 'unique-search@example.test',
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        $request = Request::create('/', 'GET', ['q' => 'unique-search']);
        $query = $this->subject->paginate($request, function ($q, string $term): void {
            $q->orWhereHas('emails', function ($emailQuery) use ($term) {
                $emailQuery->where('email', 'LIKE', "%{$term}%");
            });
        });

        $ids = $query->pluck('id_player')->all();

        $this->assertSame([$match->id_player], $ids);
    }
}
