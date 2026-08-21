<?php

namespace Blunx\AI\Support;

/**
 * Safety net: ensures a SQL query is a read-only SELECT/WITH before execution.
 *
 * Even if a query is altered between generation (cloud) and execution (this
 * package), it will never run unless it is a read-only SELECT/WITH.
 *
 * Defense in depth: the reliable write-lock remains a dedicated read-only
 * database user (GRANT SELECT / USAGE only) used to run the queries.
 */
class SqlGuard
{
    /** DML / DDL / admin keywords that modify data, structure, privileges or
     *  session state. Deliberately broad to cover PostgreSQL, MySQL/MariaDB,
     *  SQL Server, Oracle and SQLite. */
    private const DANGEROUS_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'REPLACE', 'UPSERT',
        'LOAD', 'COPY', 'CALL', 'DO',
        'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'RENAME',
        'GRANT', 'REVOKE', 'SET', 'RESET', 'DISCARD', 'SECURITY',
        'BEGIN', 'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT',
        'DECLARE', 'OPEN', 'FETCH', 'CLOSE', 'MOVE', 'CURSOR', 'DEALLOCATE',
        'EXEC', 'EXECUTE', 'PREPARE',
        'VACUUM', 'REINDEX', 'CLUSTER', 'ANALYZE', 'LOCK', 'CHECKPOINT',
        'IMPORT', 'EXPORT', 'LISTEN', 'NOTIFY', 'UNLISTEN',
        'REASSIGN', 'OWNED',
    ];

    /** Dangerous system functions usable inside a SELECT: file writes
     *  (lo_export, pg_write_file), file reads (pg_read_file), session state
     *  changes (set_config) or DoS (pg_sleep, dblink). */
    private const DANGEROUS_FUNCTIONS = [
        'pg_sleep', 'pg_read_file', 'pg_read_binary_file', 'pg_write_file',
        'pg_ls_dir', 'pg_ls_logdir', 'pg_ls_waldir', 'pg_stat_file',
        'lo_import', 'lo_export', 'lo_from_bytea', 'lo_put', 'lo_unlink',
        'dblink', 'dblink_connect', 'dblink_exec', 'dblink_send_query',
        'pg_reload_conf', 'pg_rotate_logfile', 'pg_switch_wal', 'pg_switch_xlog',
        'pg_create_restore_point', 'pg_terminate_backend', 'pg_cancel_backend',
        'pg_export_snapshot', 'set_config', 'pg_execute_server_program',
    ];

    /**
     * Whether a query is a read-only SELECT/WITH.
     */
    public static function isReadOnly(string $sql): bool
    {
        return self::validate($sql) === null;
    }

    /**
     * Return the blocking reason, or null if the query is allowed.
     * Examples: "invalid statement type: INSERT (only SELECT allowed)",
     * "forbidden keyword found: DROP", "forbidden function call found: pg_sleep".
     */
    public static function validate(string $sql): ?string
    {
        // Strip string literals and comments so data values (e.g. status = 'open')
        // do not produce false positives.
        $checkable = self::sanitize($sql);
        $upper     = strtoupper(trim($checkable));

        if ($upper === '') {
            return 'empty query';
        }

        // 1. The query must start with SELECT or WITH
        $parts     = preg_split('/\s+/', $upper, 2);
        $firstWord = $parts[0] ?? '';
        if ($firstWord !== 'SELECT' && $firstWord !== 'WITH') {
            return "invalid statement type: {$firstWord} (only SELECT allowed)";
        }

        // 2. Dangerous DML/DDL keywords, anywhere in the query
        foreach (self::DANGEROUS_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $checkable)) {
                return "forbidden keyword found: {$keyword}";
            }
        }

        // 2b. Dangerous system function calls
        foreach (self::DANGEROUS_FUNCTIONS as $fn) {
            if (preg_match('/\b' . preg_quote($fn, '/') . '\s*\(/i', $checkable)) {
                return "forbidden function call found: {$fn}";
            }
        }

        // 2c. SELECT ... INTO (creates a table or writes a file).
        //     INTO @var (variable assignment) is not caught: benign.
        if (preg_match('/\bINTO\b\s+[`"\[]?[A-Za-z_][A-Za-z0-9_]*/i', $checkable)) {
            return 'SELECT ... INTO is forbidden (would create a table or write a file)';
        }

        return null;
    }

    /**
     * Strip string literals ('...', "...", `...`) and comments (--, #, /* *\/)
     * by replacing them with spaces. SQL structure (keywords, table names)
     * stays visible but data values no longer trigger false positives.
     */
    private static function sanitize(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $i   = 0;

        while ($i < $len) {
            $c = $sql[$i];

            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $out  .= ' ';
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === $quote) {
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) { // escaping '' / "" / ``
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    if ($sql[$i] === '\\' && $i + 1 < $len) { // backslash escaping
                        $i += 2;
                        continue;
                    }
                    $out .= ' ';
                    $i++;
                }
                $out .= ' ';
            } elseif ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
            } elseif ($c === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
            } elseif ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i = min($i + 2, $len);
            } else {
                $out .= $c;
                $i++;
            }
        }

        return $out;
    }
}
