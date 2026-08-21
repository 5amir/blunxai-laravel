<?php

namespace Blunx\AI\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Blunx\AI\Support\BlunxDatabase;

/**
 * PostgreSQL schema scanner (pg_catalog / information_schema).
 */
class PostgreSqlDriver implements DatabaseDriverInterface
{
    protected string $schema;

    public function __construct(?string $schema = null)
    {
        // PostgreSQL schema to scan, configurable via config('blunx.pgsql_schema')
        // or the BLUNX_PGSQL_SCHEMA env var. Defaults to 'public'.
        $this->schema = $schema ?? Config::get('blunx.pgsql_schema', 'public');
    }

    public function getTables(string $database, array $excluded = []): array
    {
        // PostgreSQL uses pg_catalog or information_schema
        return collect(DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT tablename 
            FROM pg_tables 
            WHERE schemaname = ?
        ", [$this->schema]))
            ->map(fn($t) => $t->tablename)
            ->filter(fn($t) => !in_array($t, $excluded))
            ->values()
            ->toArray();
    }

    public function getColumnType(string $table, string $column, string $database): ?string
    {
        try {
            // For PostgreSQL, fetch the exact type from the configured schema
            $details = DB::connection(BlunxDatabase::mainConnection())->select("
                SELECT data_type, udt_name
                FROM information_schema.columns
                WHERE table_schema = ? 
                  AND table_name = ? 
                  AND column_name = ?
            ", [$this->schema, $table, $column]);

            if (!empty($details)) {
                $rawType = $details[0]->data_type;
                // Check for a custom type (enum / array)
                if ($details[0]->udt_name && str_starts_with($details[0]->udt_name, '_')) {
                    $rawType = $details[0]->udt_name;
                }
                return $rawType;
            }
        } catch (\Exception $e) {
            // fall back to the generic connection type
        }

        // Fallback: generic type via the connection's search_path
        try {
            return Schema::connection(BlunxDatabase::mainConnection())->getColumnType($table, $column);
        } catch (\Exception $e) {
            return 'text';
        }
    }

    public function getRelations(string $table, string $database): array
    {
        // Reliable source: pg_constraint (contype = 'f' = FOREIGN KEY).
        // conrelid/conkey  → child table (the one holding the FK)
        // confrelid/confkey → referenced table + referenced column
        // LATERAL unnest expands composite keys (like MySQL).
        $rows = DB::connection(BlunxDatabase::mainConnection())->select("
            SELECT
                (SELECT a.attname
                   FROM pg_attribute a
                  WHERE a.attrelid = con.conrelid AND a.attnum = cc.conkey)  AS column_name,
                reftbl.relname AS ref_table,
                (SELECT a.attname
                   FROM pg_attribute a
                  WHERE a.attrelid = con.confrelid AND a.attnum = cc.confkey) AS ref_column
            FROM pg_constraint con
            JOIN pg_class tbl    ON tbl.oid    = con.conrelid
            JOIN pg_namespace ns ON ns.oid     = con.connamespace
            JOIN pg_class reftbl ON reftbl.oid = con.confrelid
            CROSS JOIN LATERAL unnest(con.conkey, con.confkey) AS cc(conkey, confkey)
            WHERE con.contype = 'f'
              AND ns.nspname = ?
              AND tbl.relname = ?
            ORDER BY cc.conkey
        ", [$this->schema, $table]);

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
                SELECT data_type, udt_name
                FROM information_schema.columns
                WHERE table_schema = ? 
                  AND table_name = ? 
                  AND column_name = ?
            ", [$this->schema, $table, $column]);

            if (!empty($details)) {
                return $details[0]->data_type;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}