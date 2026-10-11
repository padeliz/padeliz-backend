<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConvertDatabaseCollationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_uses_utf8mb4_0900_ai_ci_after_migrations(): void
    {
        $database = (string) config('database.connections.' . config('database.default') . '.database');
        $prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');

        $row = DB::connection(config('database.default'))->selectOne(
            <<<'SQL'
SELECT TABLE_COLLATION AS collation_name
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = ?
  AND TABLE_NAME = ?
SQL,
            [$database, $prefix . 'users']
        );

        $this->assertSame('utf8mb4_0900_ai_ci', $row?->collation_name);
    }
}
