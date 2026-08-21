<?php

namespace Blunx\AI\Support\Database;

use Illuminate\Support\Facades\DB;
use Blunx\AI\Support\BlunxDatabase;

/**
 * SQL Server schema scanner (INFORMATION_SCHEMA / sys tables).
 */
class SqlServerDriver implements DatabaseDriverInterface
{
    public function getTables(string $database, array $excluded = []): array
    {
        return collect(DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
        "))
            ->map(fn($t) => $t->TABLE_NAME)
            ->filter(fn($t) => !in_array($t, $excluded))
            ->values()
            ->toArray();
    }

    public function getColumnType(string $table, string $column, string $database): ?string
    {
        try {
            $details = DB::connection(BlunxDatabase::mainConnection())->select("
                SELECT DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ", [$table, $column]);

            if (!empty($details)) {
                $type = $details[0]->DATA_TYPE;
                // Normalize some SQL Server types to generic equivalents
                return match (strtolower($type)) {
                    'nvarchar', 'varchar', 'char', 'nchar', 'ntext', 'text' => 'string',
                    'int', 'bigint', 'smallint', 'tinyint' => 'integer',
                    'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney' => 'float',
                    'datetime', 'datetime2', 'smalldatetime' => 'datetime',
                    'bit' => 'boolean',
                    default => $type,
                };
            }
            return 'string';
        } catch (\Exception $e) {
            return 'string';
        }
    }

    public function getRelations(string $table, string $database): array
    {
        $rows = DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT
                COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS column_name,
                OBJECT_NAME(fkc.referenced_object_id)               AS ref_table,
                COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS ref_column
            FROM sys.foreign_key_columns fkc
            JOIN sys.foreign_keys fk
                ON fk.object_id = fkc.constraint_object_id
            WHERE OBJECT_NAME(fkc.parent_object_id) = ?
        ", [$table]);

        return collect($rows)
            ->map(fn($r) => [
                'column'     => $r->column_name,
                'references' => "{$r->ref_table}.{$r->ref_column}",
            ])
            ->toArray();
    }

    public function getRawColumnType(string $table, string $column, string $database): ?string
    {
        try {
            $details = DB::connection(BlunxDatabase::mainConnection())->select("
                SELECT DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ", [$table, $column]);

            return $details[0]->DATA_TYPE ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
