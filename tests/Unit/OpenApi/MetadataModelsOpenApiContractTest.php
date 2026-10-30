<?php

declare(strict_types=1);

namespace Tests\Unit\OpenApi;

use App\Services\Cache\ModelSchemaCatalog;
use Tests\TestCase;

/**
 * Every Manager metadata schema must exist in the generated OpenAPI document.
 */
class MetadataModelsOpenApiContractTest extends TestCase
{
    public function test_every_metadata_schema_exists_in_openapi_v1(): void
    {
        $swaggerFile = storage_path('api-docs/api-docs-v1.json');
        $this->assertFileExists(
            $swaggerFile,
            'OpenAPI docs must be generated before tests (php artisan api:docs:generate-all).'
        );

        $swaggerData = json_decode((string) file_get_contents($swaggerFile), true);
        $this->assertIsArray($swaggerData);
        $this->assertArrayHasKey('components', $swaggerData);
        $this->assertArrayHasKey('schemas', $swaggerData['components']);

        /** @var array<string, mixed> $schemas */
        $schemas = $swaggerData['components']['schemas'];

        $missing = [];

        foreach (app(ModelSchemaCatalog::class)->metadataSchemaNames() as $schemaName) {
            $lookup = $schemaName === 'PadelEvent' ? 'Event' : $schemaName;

            if (! isset($schemas[$lookup])) {
                $missing[] = "{$schemaName} (lookup={$lookup})";
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Metadata models missing from OpenAPI components.schemas: ' . implode(', ', $missing)
        );
    }
}
