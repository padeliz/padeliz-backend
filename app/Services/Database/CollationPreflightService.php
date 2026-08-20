<?php

declare(strict_types=1);

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CollationPreflightService
{
    private const TARGET_COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * @return list<array{
     *     table: string,
     *     index: string,
     *     columns: list<string>,
     *     duplicate_count: int,
     *     sample: list<string>
     * }>
     */
    public function detectUniqueCollisions(?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $prefix = (string) config("database.connections.{$connection}.prefix", '');

        $indexes = DB::connection($connection)->select(
            <<<'SQL'
SELECT
    TABLE_NAME AS table_name,
    INDEX_NAME AS index_name,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns,
    MIN(NON_UNIQUE) AS non_unique
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = ?
  AND INDEX_NAME <> 'PRIMARY'
GROUP BY TABLE_NAME, INDEX_NAME
HAVING MIN(NON_UNIQUE) = 0
SQL,
            [$database]
        );

        $collisions = [];

        foreach ($indexes as $index) {
            $table = (string) $index->table_name;
            if ($prefix !== '' && ! str_starts_with($table, $prefix)) {
                continue;
            }

            $logicalTable = $prefix !== '' ? substr($table, strlen($prefix)) : $table;
            if (! Schema::connection($connection)->hasTable($logicalTable)) {
                continue;
            }

            $columns = array_values(array_filter(explode(',', (string) $index->columns)));
            if ($columns === []) {
                continue;
            }

            $columnTypes = $this->columnTypes($connection, $database, $table, $columns);
            $textColumns = array_values(array_filter(
                $columns,
                static fn (string $column): bool => ($columnTypes[$column] ?? '') === 'text'
            ));
            if ($textColumns === []) {
                continue;
            }

            $groupExprs = [];
            $sampleSelectParts = [];
            foreach ($columns as $column) {
                if (($columnTypes[$column] ?? '') === 'text') {
                    $groupExprs[] = "LOWER(CONVERT(`{$column}` USING utf8mb4) COLLATE ".self::TARGET_COLLATION.')';
                    $sampleSelectParts[] = "MIN(`{$column}`) AS `{$column}`";
                } else {
                    $groupExprs[] = "`{$column}`";
                    $sampleSelectParts[] = "MIN(`{$column}`) AS `{$column}`";
                }
            }

            $sampleSelect = implode(', ', $sampleSelectParts);
            $groupBy = implode(', ', $groupExprs);

            $rows = DB::connection($connection)->select(
                "SELECT {$sampleSelect}, COUNT(*) AS duplicate_count
                 FROM `{$table}`
                 GROUP BY {$groupBy}
                 HAVING duplicate_count > 1
                 LIMIT 5",
            );

            if ($rows === []) {
                continue;
            }

            $total = (int) DB::connection($connection)->selectOne(
                "SELECT COUNT(*) AS total FROM (
                    SELECT 1
                    FROM `{$table}`
                    GROUP BY {$groupBy}
                    HAVING COUNT(*) > 1
                ) AS grouped"
            )->total;

            $samples = [];
            foreach ($rows as $row) {
                $parts = [];
                foreach ($columns as $column) {
                    $parts[] = "{$column}=".((string) ($row->{$column} ?? ''));
                }
                $samples[] = implode(', ', $parts);
            }

            $collisions[] = [
                'table' => $table,
                'index' => (string) $index->index_name,
                'columns' => $columns,
                'duplicate_count' => $total,
                'sample' => $samples,
            ];
        }

        return $collisions;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, 'text'|'other'>
     */
    private function columnTypes(
        string $connection,
        string $database,
        string $table,
        array $columns,
    ): array {
        $types = [];
        foreach ($columns as $column) {
            $row = DB::connection($connection)->selectOne(
                <<<'SQL'
SELECT DATA_TYPE AS data_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = ?
  AND TABLE_NAME = ?
  AND COLUMN_NAME = ?
SQL,
                [$database, $table, $column]
            );
            if ($row === null) {
                continue;
            }
            $type = strtolower((string) $row->data_type);
            $types[$column] = in_array($type, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'], true)
                ? 'text'
                : 'other';
        }

        return $types;
    }

    /**
     * @param  list<array<string, mixed>>  $collisions
     */
    public function formatCollisionReport(array $collisions): string
    {
        if ($collisions === []) {
            return 'No UNIQUE index collisions detected under '.self::TARGET_COLLATION.'.';
        }

        $lines = ['UNIQUE index collisions detected under '.self::TARGET_COLLATION.':'];
        foreach ($collisions as $collision) {
            $lines[] = sprintf(
                '- %s.%s (%s): %d collision group(s); sample: %s',
                $collision['table'],
                $collision['index'],
                implode(', ', $collision['columns']),
                $collision['duplicate_count'],
                implode(' | ', $collision['sample'])
            );
        }

        return implode(PHP_EOL, $lines);
    }
}
