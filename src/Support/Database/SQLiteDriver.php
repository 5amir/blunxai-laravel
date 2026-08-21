<?php

namespace Blunx\AI\Support\Database;

use Illuminate\Support\Facades\DB;
use Blunx\AI\Support\BlunxDatabase;

/**
 * SQLite schema scanner (sqlite_master / PRAGMA).
 */
class SQLiteDriver implements DatabaseDriverInterface
{
    public function getTables(string $database, array $excluded = []): array
    {
        return collect(DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
              AND name NOT LIKE 'sqlite_%'
        "))
            ->map(fn($t) => $t->name)
            ->filter(fn($t) => !in_array($t, $excluded))
            ->values()
            ->toArray();
    }

    public function getColumnType(string $table, string $column, string $database): ?string
    {
        try {
            $columns = DB::connection(BlunxDatabase::mainConnection())->select("PRAGMA table_info('{$table}')");
            foreach ($columns as $col) {
                if ($col->name === $column) {
                    return $col->type;
                }
            }
            return 'text';
        } catch (\Exception $e) {
            return 'text';
        }
    }

    public function getRelations(string $table, string $database): array
    {
        $rows = DB::connection(BlunxDatabase::mainConnection())->select("PRAGMA foreign_key_list('{$table}')");

        return collect($rows)
            ->map(fn($r) => [
                'column'     => $r->from,
                'references' => "{$r->table}.{$r->to}",
            ])
            ->toArray();
    }

    public function getRawColumnType(string $table, string $column, string $database): ?string
    {
        try {
            $columns = DB::connection(BlunxDatabase::mainConnection())->select("PRAGMA table_info('{$table}')");
            foreach ($columns as $col) {
                if ($col->name === $column) {
                    return $col->type;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
