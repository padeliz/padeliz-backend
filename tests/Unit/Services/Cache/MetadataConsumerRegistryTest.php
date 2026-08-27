<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cache;

use App\Models\Challenge\ChallengeSession;
use App\Services\Cache\MetadataConsumerRegistry;
use App\Services\Cache\ModelSchemaCatalog;
use Tests\TestCase;

class MetadataConsumerRegistryTest extends TestCase
{
    public function test_registry_discovers_nested_trait_model(): void
    {
        $catalog = app(ModelSchemaCatalog::class);
        $catalog->reset();
        app(MetadataConsumerRegistry::class)->reset();

        $this->assertContains(ChallengeSession::class, $catalog->metadataClasses());

        // ChallengeSession has no *_id filterables, so it must not be required in map() edges.
        $map = app(MetadataConsumerRegistry::class)->map();
        $this->assertIsArray($map);
    }

    public function test_registry_lists_challenge_session_among_eloquent_models(): void
    {
        $catalog = app(ModelSchemaCatalog::class);
        $catalog->reset();

        $this->assertContains(ChallengeSession::class, $catalog->eloquentClasses());
        $this->assertContains(ChallengeSession::class, $catalog->metadataClasses());
    }
}
