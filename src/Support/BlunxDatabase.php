<?php

namespace Blunx\AI\Support;

use Illuminate\Support\Facades\DB;
use Blunx\AI\Support\Database\DsnParser;

/**
 * Dedicated database connection for Blunx queries.
 *
 * The connection can be defined in two ways:
 *   1. via a connection URL (DSN) — `blunx.db_url` / `BLUNX_DB_URL`
 *      (e.g. mysql://user:pass@host:3306/db?sslmode=require, postgres://…,
 *      sqlite://…) — a `blunx` connection is then registered from the URL;
 *   2. via the application's default connection (`database.default`).
 *
 * If a read-only user is configured (blunx.readonly_db), all Blunx queries go
 * through a dedicated connection with those credentials — a role that can only
 * read (SELECT / USAGE). Otherwise the main connection is used.
 *
 * Complements SqlGuard: even a malicious query that bypassed validation could
 * not write anything with a read-only role.
 */
class BlunxDatabase
{
    /** Laravel connection name used when a DSN is provided. */
    public const MAIN_CONNECTION = 'blunx';

    /** Laravel connection name for the read-only role. */
    private const READONLY_CONNECTION = 'blunx_readonly';

    /**
     * Register the `blunx` (and optionally `blunx_readonly`) connections from
     * the DSN, when `blunx.db_url` is defined.
     */
    public static function registerFromDsn(): void
    {
        $dbUrl = (string) config('blunx.db_url', '');

        if ($dbUrl === '') {
            return;
        }

        $cfg = DsnParser::toLaravelConfig($dbUrl);
        config(['database.connections.'.self::MAIN_CONNECTION => $cfg]);
        DB::purge(self::MAIN_CONNECTION);

        // Read-only connection derived from the DSN if a read-only role is given.
        $username = (string) config('blunx.readonly_db.username', '');
        if ($username !== '') {
            $ro = $cfg;
            $ro['username'] = $username;
            $ro['password'] = (string) config('blunx.readonly_db.password', '');
            unset($ro['read'], $ro['write']);
            config(['database.connections.'.self::READONLY_CONNECTION => $ro]);
            DB::purge(self::READONLY_CONNECTION);
        }
    }

    /**
     * Connection name for schema scanning and Blunx tables.
     *
     * - `blunx.db_url` set → `blunx` connection (registered from the DSN).
     * - otherwise → the application's default connection.
     */
    public static function mainConnection(): string
    {
        if ((string) config('blunx.db_url', '') !== '') {
            self::registerFromDsn();

            return self::MAIN_CONNECTION;
        }

        return (string) config('database.default');
    }

    /**
     * Connection name to use for Blunx queries.
     *
     * - `blunx.db_url` set → `blunx` connection (or `blunx_readonly` if a
     *   read-only role is provided).
     * - otherwise: no read-only role → the application's default connection;
     *   read-only role → `blunx_readonly` built from the default connection
     *   (same driver/host/database) with the read-only credentials.
     */
    public static function connection(): string
    {
        $dbUrl = (string) config('blunx.db_url', '');

        if ($dbUrl !== '') {
            self::registerFromDsn();
            $username = (string) config('blunx.readonly_db.username', '');

            // ⚠️ With a DSN, the read-only role is OPTIONAL:
            //    - if the URL already carries a read-only role (created in the DBMS),
            //      readonly_db is empty → use the 'blunx' connection (the URL) as-is;
            //    - if the URL carries the MAIN role and read-only is wanted,
            //      readonly_db is set → use the derived 'blunx_readonly' connection.
            return $username === '' ? self::MAIN_CONNECTION : self::READONLY_CONNECTION;
        }

        $username = (string) config('blunx.readonly_db.username', '');

        if ($username === '') {
            return (string) config('database.default');
        }

        $default = (string) config('database.default');
        $baseCfg = config('database.connections.'.$default);

        if (!is_array($baseCfg)) {
            return $default;
        }

        $cfg = $baseCfg;
        $cfg['username'] = $username;
        $cfg['password'] = (string) config('blunx.readonly_db.password', '');
        // Do not inherit any read/write blocks from the source connection:
        // the read-only role must be used as-is.
        unset($cfg['read'], $cfg['write']);

        config(['database.connections.'.self::READONLY_CONNECTION => $cfg]);
        DB::purge(self::READONLY_CONNECTION);

        return self::READONLY_CONNECTION;
    }

    /**
     * Run a read-only SQL query and return the rows.
     *
     * @param  string $sql
     * @return array<int, object>
     */
    public static function select(string $sql): array
    {
        return DB::connection(self::connection())->select($sql);
    }
}
