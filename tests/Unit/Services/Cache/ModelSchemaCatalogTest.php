<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cache;

use App\Models\Challenge\ChallengeSession;
use App\Models\Club;
use App\Services\Cache\ModelSchemaCatalog;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Tests\Fixtures\ModelSchemaCatalog\Nested\NestedFoo;
use Tests\Fixtures\ModelSchemaCatalog\NotAModel;
use Tests\Fixtures\ModelSchemaCatalog\RootBar;
use Tests\Fixtures\ModelSchemaCatalog\WithoutTrait\BareModel;
use Tests\Fixtures\ModelSchemaCatalog\WithTrait\TraitedModel;
use Tests\TestCase;

class ModelSchemaCatalogTest extends TestCase
{
    private string $fixtureRoot;

    private string $fixtureNamespace = 'Tests\\Fixtures\\ModelSchemaCatalog';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = base_path('tests/Fixtures/ModelSchemaCatalog');
    }

    public function test_nested_relative_path_resolves_to_fqcn(): void
    {
        $catalog = $this->fixtureCatalog();

        $this->assertSame(NestedFoo::class, $catalog->classForSchema('NestedFoo'));
    }

    public function test_root_relative_path_resolves_to_fqcn(): void
    {
        $catalog = $this->fixtureCatalog();

        $this->assertSame(RootBar::class, $catalog->classForSchema('RootBar'));
    }

    public function test_production_nested_resolve(): void
    {
        $catalog = app(ModelSchemaCatalog::class);

        $this->assertSame(ChallengeSession::class, $catalog->classForSchema('ChallengeSession'));
    }

    public function test_production_root_resolve(): void
    {
        $catalog = app(ModelSchemaCatalog::class);

        $this->assertSame(Club::class, $catalog->classForSchema('Club'));
    }

    public function test_basename_collision_throws(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'padeliz_schema_collision_' . uniqid('', true);
        mkdir($root . DIRECTORY_SEPARATOR . 'Left', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'Right', 0777, true);

        $ns = 'Tests\\Fixtures\\ModelSchemaCatalogCollision\\' . str_replace('.', '', uniqid('N', true));
        $leftClass = $ns . '\\Left\\SameName';
        $rightClass = $ns . '\\Right\\SameName';

        $this->writeModelFixture($root . DIRECTORY_SEPARATOR . 'Left' . DIRECTORY_SEPARATOR . 'SameName.php', $ns . '\\Left', 'SameName');
        $this->writeModelFixture($root . DIRECTORY_SEPARATOR . 'Right' . DIRECTORY_SEPARATOR . 'SameName.php', $ns . '\\Right', 'SameName');

        require_once $root . DIRECTORY_SEPARATOR . 'Left' . DIRECTORY_SEPARATOR . 'SameName.php';
        require_once $root . DIRECTORY_SEPARATOR . 'Right' . DIRECTORY_SEPARATOR . 'SameName.php';

        $catalog = new ModelSchemaCatalog($root, $ns);

        try {
            $catalog->eloquentClasses();
            $this->fail('Expected InvalidArgumentException for duplicate basename');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Duplicate model schema basename SameName', $e->getMessage());
            $this->assertStringContainsString($leftClass, $e->getMessage());
            $this->assertStringContainsString($rightClass, $e->getMessage());
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_unknown_schema_throws(): void
    {
        $catalog = $this->fixtureCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class for schema DoesNotExist not found');

        $catalog->classForSchema('DoesNotExist');
    }

    public function test_non_model_php_ignored(): void
    {
        $catalog = $this->fixtureCatalog();
        $classes = $catalog->eloquentClasses();

        $this->assertContains(RootBar::class, $classes);
        $this->assertNotContains(NotAModel::class, $classes);
        $this->assertFalse(is_subclass_of(NotAModel::class, Model::class));
    }

    public function test_metadata_classes_only_includes_trait_users(): void
    {
        $catalog = $this->fixtureCatalog();
        $metadata = $catalog->metadataClasses();

        $this->assertContains(TraitedModel::class, $metadata);
        $this->assertNotContains(BareModel::class, $metadata);
        $this->assertNotContains(RootBar::class, $metadata);
    }

    public function test_class_for_metadata_schema_accepts_trait_users(): void
    {
        $catalog = $this->fixtureCatalog();

        $this->assertSame(TraitedModel::class, $catalog->classForMetadataSchema('TraitedModel'));
    }

    public function test_class_for_metadata_schema_rejects_bare_models(): void
    {
        $catalog = $this->fixtureCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class for schema BareModel not found');

        $catalog->classForMetadataSchema('BareModel');
    }

    public function test_production_class_for_metadata_schema_accepts_club_and_event(): void
    {
        $catalog = app(ModelSchemaCatalog::class);

        $this->assertSame(Club::class, $catalog->classForMetadataSchema('Club'));
        $this->assertSame(\App\Models\PadelEvent::class, $catalog->classForMetadataSchema('Event'));
    }

    public function test_production_metadata_schema_names_include_event_alias(): void
    {
        $catalog = app(ModelSchemaCatalog::class);
        $names = $catalog->metadataSchemaNames();

        $this->assertContains('Club', $names);
        $this->assertContains('PadelEvent', $names);
        $this->assertContains('Event', $names);
        $this->assertNotContains('BankTransferSettings', $names);
    }

    public function test_memoization_and_reset(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'padeliz_schema_memo_' . uniqid('', true);
        mkdir($root, 0777, true);

        $ns = 'Tests\\Fixtures\\ModelSchemaCatalogMemo\\' . str_replace('.', '', uniqid('N', true));
        $firstPath = $root . DIRECTORY_SEPARATOR . 'FirstModel.php';
        $secondPath = $root . DIRECTORY_SEPARATOR . 'SecondModel.php';

        $this->writeModelFixture($firstPath, $ns, 'FirstModel');
        require_once $firstPath;

        $catalog = new ModelSchemaCatalog($root, $ns);
        $first = $catalog->eloquentClasses();
        $this->assertSame([$ns . '\\FirstModel'], $first);

        $this->writeModelFixture($secondPath, $ns, 'SecondModel');
        require_once $secondPath;

        $this->assertSame($first, $catalog->eloquentClasses());

        $catalog->reset();
        $afterReset = $catalog->eloquentClasses();
        $this->assertContains($ns . '\\FirstModel', $afterReset);
        $this->assertContains($ns . '\\SecondModel', $afterReset);

        $this->deleteDirectory($root);
    }

    private function fixtureCatalog(): ModelSchemaCatalog
    {
        return new ModelSchemaCatalog($this->fixtureRoot, $this->fixtureNamespace);
    }

    private function writeModelFixture(string $path, string $namespace, string $class): void
    {
        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Database\\Eloquent\\Model;

class {$class} extends Model
{
    protected \$table = 'fixture_{$class}';
}
PHP;
        file_put_contents($path, $code);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
