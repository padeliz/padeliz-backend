<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use App\Services\ResourceMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceMetadataForeignKeyFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_searchable_fields_falls_back_to_primary_key_label(): void
    {
        $this->assertSame([], EditionLotteryPrize::$searchableFields);

        EditionLotteryPrize::factory()->count(2)->create();

        $service = app(ResourceMetadataService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getForeignKeyMetadata');
        $method->setAccessible(true);

        $meta = $method->invoke($service, EditionLotteryRule::class, 'lottery_prize_id');

        $this->assertSame('foreign_key', $meta['type']);
        $this->assertSame('EditionLotteryPrize', $meta['related_schema_name']);
        $this->assertSame('id_lottery_prize', $meta['related_schema_field']);
        $this->assertIsObject($meta['values']);
        $this->assertNotEmpty((array) $meta['values']);
    }

    public function test_edition_lottery_rule_metadata_endpoint_loads(): void
    {
        $manager = \App\Models\Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/manager/metadata/EditionLotteryRule');
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertArrayHasKey(
            'lottery_prize_id',
            $response->json('data.filterable_fields') ?? []
        );
    }
}
