<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Services\Schedule\TemplateCombinations;
use App\Services\Schedule\TemplateMatchesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemplatesToolsControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private int $latestVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->latestVersion = (new TemplateMatchesRepository(resource_path('template-matches')))->latestVersion();
        Sanctum::actingAs($this->manager);
    }

    public function test_catalog_lists_mixed_combos_by_default(): void
    {
        $response = $this->getJson('/api/v1/manager/tools/templates/catalog');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.include_fixed_teams', false)
            ->assertJsonPath('data.version', $this->latestVersion);

        $combos = $response->json('data.combos');
        $this->assertIsArray($combos);
        $this->assertNotEmpty($combos);

        foreach ($combos as $combo) {
            $this->assertFalse($combo['fixed_teams']);
        }

        $expectedCount = 0;
        foreach (TemplateCombinations::COMBINATIONS as $partnersList) {
            $expectedCount += count($partnersList);
        }
        $inExpected = array_values(array_filter(
            $combos,
            static fn (array $c): bool => $c['in_expected_catalog'] === true
        ));
        $this->assertCount($expectedCount, $inExpected);
    }

    public function test_catalog_can_include_fixed_teams(): void
    {
        // Committed -fixedteams JSON currently lives under v1 only.
        $response = $this->getJson('/api/v1/manager/tools/templates/catalog?include_fixed_teams=1&templates_version=1');

        $response->assertOk()
            ->assertJsonPath('data.include_fixed_teams', true)
            ->assertJsonPath('data.version', 1);

        $hasFixed = collect($response->json('data.combos'))
            ->contains(static fn (array $c): bool => $c['fixed_teams'] === true);

        $this->assertTrue($hasFixed, 'Expected at least one fixed_teams combo when include_fixed_teams=1');
    }

    public function test_demo_returns_mixed_template_structure(): void
    {
        $response = $this->getJson('/api/v1/manager/tools/templates/demo/8/4/1/1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.players', 8)
            ->assertJsonPath('data.partners', 4)
            ->assertJsonPath('data.repeat', 1)
            ->assertJsonPath('data.courts', 1)
            ->assertJsonPath('data.fixedTeams', false)
            ->assertJsonStructure([
                'data' => [
                    'matches',
                    'metrics',
                    'eligible',
                    'usable',
                    'version',
                ],
            ]);
    }

    public function test_demo_returns_404_for_unknown_combo(): void
    {
        $this->getJson('/api/v1/manager/tools/templates/demo/99/99/1/1')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_metrics_returns_summary_for_mixed_catalog(): void
    {
        $response = $this->getJson('/api/v1/manager/tools/templates/metrics');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.include_fixed_teams', false)
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'latest_version',
                    'missing_count',
                    'rows',
                    'averages' => [
                        'min_partners_fairness',
                        'avg_partners_fairness',
                        'min_distribution',
                        'avg_distribution',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.rows'));
        $this->assertSame(0, $response->json('data.missing_count'));
    }

    public function test_metrics_include_fixed_teams_lists_disk_identities(): void
    {
        $response = $this->getJson('/api/v1/manager/tools/templates/metrics?include_fixed_teams=1&templates_version=1');

        $response->assertOk()
            ->assertJsonPath('data.include_fixed_teams', true)
            ->assertJsonPath('data.version', 1);

        $hasFixed = collect($response->json('data.rows'))
            ->contains(static fn (array $row): bool => $row['fixed_teams'] === true);

        $this->assertTrue($hasFixed);
    }

    public function test_requires_manager_auth(): void
    {
        Sanctum::actingAs(Player::factory()->create(['user_types' => ['player']]));

        $this->getJson('/api/v1/manager/tools/templates/catalog')->assertForbidden();
    }
}
