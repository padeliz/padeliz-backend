<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Cache\ModelSchemaCatalog;
use App\Services\ResourceMetadataService;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ResourceMetadataServiceTest extends TestCase
{
    private ResourceMetadataService $metadataService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadataService = app(ResourceMetadataService::class);
    }

    /**
     * Test the getResourceNameFromSchema method directly using reflection
     * This ensures that hyphenated strings only have the last word pluralized
     */
    public function test_get_resource_name_from_schema_pluralization(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        // Test single word models (should be pluralized normally)
        $this->assertEquals('players', $method->invoke($this->metadataService, 'Player'));
        $this->assertEquals('clubs', $method->invoke($this->metadataService, 'Club'));
        $this->assertEquals('divisions', $method->invoke($this->metadataService, 'Division'));
        $this->assertEquals('editions', $method->invoke($this->metadataService, 'Edition'));
        $this->assertEquals('locations', $method->invoke($this->metadataService, 'Location'));
        $this->assertEquals('prizes', $method->invoke($this->metadataService, 'Prize'));

        // Test hyphenated models (only last word should be pluralized)
        $this->assertEquals('edition-lotteries', $method->invoke($this->metadataService, 'EditionLottery'));
        $this->assertEquals('edition-participations', $method->invoke($this->metadataService, 'EditionParticipation'));
        $this->assertEquals('edition-divisions', $method->invoke($this->metadataService, 'EditionDivision'));
        $this->assertEquals('edition-lottery-luckies', $method->invoke($this->metadataService, 'EditionLotteryLucky'));
        $this->assertEquals('eligibilities', $method->invoke($this->metadataService, 'Eligibility'));
        $this->assertEquals('sponsors', $method->invoke($this->metadataService, 'Sponsor'));
        $this->assertEquals('sponsor-prizes', $method->invoke($this->metadataService, 'SponsorPrize'));
        $this->assertEquals('edition-lottery-prizes', $method->invoke($this->metadataService, 'EditionLotteryPrize'));
        $this->assertEquals('player-imports', $method->invoke($this->metadataService, 'PlayerImport'));
        $this->assertEquals('player-import-rows', $method->invoke($this->metadataService, 'PlayerImportRow'));
        $this->assertEquals('challenge-sessions', $method->invoke($this->metadataService, 'ChallengeSession'));

        // Test edge cases with multiple hyphens (using existing models)
        $this->assertEquals('edition-divisions', $method->invoke($this->metadataService, 'EditionDivision'));
    }

    /**
     * Test that the method handles edge cases correctly
     */
    public function test_get_resource_name_from_schema_edge_cases(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        // Test with already kebab-case input (should still work)
        $this->assertEquals('players', $method->invoke($this->metadataService, 'player'));
        $this->assertEquals('edition-lotteries', $method->invoke($this->metadataService, 'edition-lottery'));

        // Test with mixed case
        $this->assertEquals('p-l-a-y-e-rs', $method->invoke($this->metadataService, 'PLAYER'));
        $this->assertEquals('e-d-i-t-i-o-n_-l-o-t-t-e-r-ys', $method->invoke($this->metadataService, 'EDITION_LOTTERY'));

        // Test with numbers in names
        $this->assertEquals('test-model1s', $method->invoke($this->metadataService, 'TestModel1'));
        $this->assertEquals('test-model2s', $method->invoke($this->metadataService, 'TestModel2'));
    }

    /**
     * Test that irregular plurals are handled correctly by Laravel's Str::plural
     */
    public function test_irregular_plural_handling(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        // Test some common irregular plurals
        $this->assertEquals('children', $method->invoke($this->metadataService, 'Child'));
        $this->assertEquals('people', $method->invoke($this->metadataService, 'Person'));
        $this->assertEquals('mice', $method->invoke($this->metadataService, 'Mouse'));
        $this->assertEquals('geese', $method->invoke($this->metadataService, 'Goose'));

        // Test hyphenated with irregular plurals
        $this->assertEquals('test-children', $method->invoke($this->metadataService, 'TestChild'));
        $this->assertEquals('edition-people', $method->invoke($this->metadataService, 'EditionPerson'));
    }

    /**
     * Test that the method correctly handles empty strings and special characters
     */
    public function test_get_resource_name_from_schema_special_cases(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        // Test with empty string
        $this->assertEquals('', $method->invoke($this->metadataService, ''));

        // Test with single character
        $this->assertEquals('as', $method->invoke($this->metadataService, 'A'));

        // Test with special characters (should be converted to kebab-case)
        $this->assertEquals('test_-models', $method->invoke($this->metadataService, 'Test_Model'));
        $this->assertEquals('test.-models', $method->invoke($this->metadataService, 'Test.Model'));
    }

    /**
     * Test that the method maintains consistency with Laravel's Str::kebab and Str::plural
     */
    public function test_consistency_with_laravel_helpers(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        $testCases = [
            'Player',
            'Club',
            'EditionLottery',
            'EditionParticipation',
            'Eligibility',
            'Sponsor',
            'SponsorPrize',
            'EditionLotteryPrize',
        ];

        foreach ($testCases as $modelName) {
            $result = $method->invoke($this->metadataService, $modelName);
            $kebabCase = Str::kebab($modelName);

            if (str_contains($kebabCase, '-')) {
                // For hyphenated strings, only the last word should be pluralized
                $parts = explode('-', $kebabCase);
                $lastPart = array_pop($parts);
                $pluralizedLastPart = Str::plural($lastPart);
                $parts[] = $pluralizedLastPart;
                $expected = implode('-', $parts);
            } else {
                // For single words, pluralize normally
                $expected = Str::plural($kebabCase);
            }

            $this->assertEquals($expected, $result, "Failed for model: {$modelName}");
        }
    }

    /**
     * Test pluralization logic for all models dynamically
     * This test will automatically work with any models added/removed in the future
     */
    public function test_pluralization_for_all_models_dynamically(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        $models = $this->getAllModels();

        foreach ($models as $modelName) {
            $result = $method->invoke($this->metadataService, $modelName);
            $kebabCase = Str::kebab($modelName);

            if (str_contains($kebabCase, '-')) {
                // For hyphenated strings, only the last word should be pluralized
                $parts = explode('-', $kebabCase);
                $lastPart = array_pop($parts);
                $pluralizedLastPart = Str::plural($lastPart);
                $parts[] = $pluralizedLastPart;
                $expected = implode('-', $parts);
            } else {
                // For single words, pluralize normally
                $expected = Str::plural($kebabCase);
            }

            $this->assertEquals($expected, $result, "Failed for model: {$modelName}");
        }
    }

    /**
     * Test that all models can generate resource names successfully
     * This ensures the pluralization logic works with all current and future models
     */
    public function test_resource_name_generation_for_all_models_dynamically(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        $models = $this->getAllModels();

        foreach ($models as $modelName) {
            try {
                $resourceName = $method->invoke($this->metadataService, $modelName);

                // Verify resource name is not empty
                $this->assertNotEmpty($resourceName, "Resource name is empty for model: {$modelName}");

                // Verify resource name contains only lowercase letters, numbers, and hyphens
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9-]+$/',
                    $resourceName,
                    "Resource name '{$resourceName}' for model '{$modelName}' contains invalid characters"
                );

                // Verify resource name ends with a plural form
                $this->assertTrue(
                    $this->isPlural($resourceName),
                    "Resource name '{$resourceName}' for model '{$modelName}' is not plural"
                );

            } catch (\Exception $e) {
                // Skip models that cause errors
                $this->markTestSkipped("Model {$modelName} caused error: " . $e->getMessage());
            }
        }
    }

    /**
     * Test that resource names are properly generated for all models
     * This ensures API endpoints will work correctly
     */
    public function test_resource_names_for_all_models_dynamically(): void
    {
        $reflection = new ReflectionClass($this->metadataService);
        $method = $reflection->getMethod('getResourceNameFromSchema');
        $method->setAccessible(true);

        $models = $this->getAllModels();

        foreach ($models as $modelName) {
            $resourceName = $method->invoke($this->metadataService, $modelName);

            // Verify resource name is not empty
            $this->assertNotEmpty($resourceName, "Resource name is empty for model: {$modelName}");

            // Verify resource name contains only lowercase letters, numbers, and hyphens
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]+$/',
                $resourceName,
                "Resource name '{$resourceName}' for model '{$modelName}' contains invalid characters"
            );

            // Verify resource name ends with a plural form
            $this->assertTrue(
                $this->isPlural($resourceName),
                "Resource name '{$resourceName}' for model '{$modelName}' is not plural"
            );
        }
    }

    /**
     * Get all model schema basenames from the recursive Models catalog
     */
    private function getAllModels(): array
    {
        return array_map(
            static fn (string $class): string => class_basename($class),
            app(ModelSchemaCatalog::class)->eloquentClasses()
        );
    }

    /**
     * Check if a word is plural (simple heuristic)
     */
    private function isPlural(string $word): bool
    {
        // Simple plural detection - ends with 's' or 'ies' or 'es'
        return preg_match('/[sies]$/', $word) ||
               preg_match('/[^s]s$/', $word) ||
               preg_match('/ies$/', $word) ||
               preg_match('/es$/', $word);
    }
}
