<?php

namespace Blunx\AI\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Blunx\AI\Support\BlunxDatabase;

/**
 * MySQL / MariaDB schema scanner (information_schema + SHOW TABLES).
 */
class MySqlDriver implements DatabaseDriverInterface
{
    public function getTables(string $database, array $excluded = []): array
    {
        $key = "Tables_in_{$database}";
        
        return collect(DB::connection(BlunxDatabase::mainConnection())->select("SHOW TABLES FROM `{$database}`"))
            ->map(fn($t) => $t->$key)
            ->filter(fn($t) => !in_array($t, $excluded))
            ->values()
            ->toArray();
    }

    public function getColumnType(string $table, string $column, string $database): ?string
    {
        try {
            $type = Schema::connection(BlunxDatabase::mainConnection())->getColumnType($table, $column);
            $details = DB::connection(BlunxDatabase::mainConnection())->select("
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ", [$database, $table, $column]);

            $raw = $details[0]->COLUMN_TYPE ?? '';
            if (str_contains($raw, 'enum')) {
                return $raw;
            }
            return $type;
        } catch (\Exception $e) {
            return 'string';
        }
    }

    public function getRelations(string $table, string $database): array
    {
        $rows = DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT
                kcu.COLUMN_NAME            AS column_name,
                kcu.REFERENCED_TABLE_NAME  AS ref_table,
                kcu.REFERENCED_COLUMN_NAME AS ref_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
               AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ?
              AND kcu.TABLE_NAME   = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ", [$database, $table]);

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
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ", [$database, $table, $column]);

            return $details[0]->COLUMN_TYPE ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}