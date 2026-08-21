<?php

namespace Blunx\AI\Support\Database;

use InvalidArgumentException;

/**
 * Database connection URL (DSN) parser.
 *
 * Converts a connection URL such as
 *   mysql://user:pass@host:3306/db?sslmode=require
 *   mariadb://user:pass@host:3306/db
 *   postgres://user:pass@host:5432/db    (or postgresql://)
 *   sqlite:///path/to/db.sqlite          (or sqlite://:memory:)
 *   sqlsrv://user:pass@host:1433/db      (or mssql://)
 * into a normalized structure, and into a Laravel connection config.
 *
 * SSL options (query string):
 *   sslmode=disable|allow|prefer|require|verify-ca|verify-full
 *   ssl=true|false|1|0
 *   ssl-ca / sslrootcert, sslcert / ssl-cert, sslkey / ssl-key
 */
class DsnParser
{
    /** Schemes → Laravel driver + default port. */
    private const SCHEMES = [
        'mysql'     => ['driver' => 'mysql', 'port' => 3306],
        'mariadb'   => ['driver' => 'mysql', 'port' => 3306],
        'postgres'  => ['driver' => 'pgsql', 'port' => 5432],
        'postgresql'=> ['driver' => 'pgsql', 'port' => 5432],
        'sqlite'    => ['driver' => 'sqlite', 'port' => null],
        'sqlsrv'    => ['driver' => 'sqlsrv', 'port' => 1433],
        'mssql'     => ['driver' => 'sqlsrv', 'port' => 1433],
    ];

    /**
     * Parse a database connection URL into a normalized structure.
     *
     * @return array{
     *   driver: string,
     *   host: string|null,
     *   port: int|null,
     *   database: string|null,
     *   username: string|null,
     *   password: string|null,
     *   ssl: array{reject_unauthorized: bool, ca?: string, cert?: string, key?: string}|null
     * }
     */
    public static function parse(string $url): array
    {
        $url = trim($url);

        // scheme://[user[:pass]@]host[:port][/database][?param=value&...][#frag]
        // (parse_url fails on sqlite:///path, hence the custom regex)
        if (!preg_match('~^([a-z][a-z0-9+.-]*):\/\/([^@/]*@)?([^/?#]*)([^?#]*)(\?[^#]*)?(#.*)?$~i', $url, $m)) {
            throw new InvalidArgumentException("Invalid database URL: {$url}");
        }

        $scheme = strtolower($m[1]);
        if (!isset(self::SCHEMES[$scheme])) {
            throw new InvalidArgumentException("Unsupported database URL scheme: {$scheme}");
        }

        $spec = self::SCHEMES[$scheme];
        $query = [];
        if (isset($m[5]) && $m[5] !== '') {
            parse_str(substr($m[5], 1), $query);
        }

        // user[:password]@
        $username = null;
        $password = null;
        $userinfo = isset($m[2]) && $m[2] !== '' ? substr($m[2], 0, -1) : '';
        if ($userinfo !== '') {
            $idx = strpos($userinfo, ':');
            if ($idx !== false) {
                $username = rawurldecode(substr($userinfo, 0, $idx));
                $password = rawurldecode(substr($userinfo, $idx + 1));
            } else {
                $username = rawurldecode($userinfo);
            }
        }

        // host[:port] (IPv6 entre crochets)
        $host = null;
        $port = null;
        $authority = $m[3] ?? '';
        if (str_starts_with($authority, '[')) {
            $close = strpos($authority, ']');
            $host = substr($authority, 1, $close !== false ? $close - 1 : null);
            $rest = $close !== false ? substr($authority, $close + 1) : '';
            if (str_starts_with($rest, ':')) {
                $port = (int) substr($rest, 1) ?: null;
            }
        } elseif ($authority !== '') {
            $idx = strrpos($authority, ':');
            if ($idx !== false && preg_match('/^\d+$/', substr($authority, $idx + 1))) {
                $host = substr($authority, 0, $idx);
                $port = (int) substr($authority, $idx + 1);
            } else {
                $host = $authority;
            }
        }

        // Path → database
        $database = null;
        $path = $m[4] ?? '';
        if ($scheme === 'sqlite') {
            // sqlite:///chemin/vers/db.sqlite → chemin dans le path
            // sqlite://:memory:               → base = authority
            $database = $path !== '' ? $path : null;
            if ($database === '/') {
                $database = null;
            }
            if ($database === null && $authority !== '' && str_starts_with($authority, ':')) {
                $database = $authority;
            }
        } else {
            $database = $path !== '' ? (rawurldecode(ltrim($path, '/')) ?: null) : null;
        }

        return [
            'driver'   => $spec['driver'],
            'host'     => $host,
            'port'     => $port ?? $spec['port'],
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'ssl'      => self::resolveSsl($query),
        ];
    }

    /**
     * Resolve SSL options from the query string.
     *
     * @param  array<string, mixed> $query
     * @return array{reject_unauthorized: bool, ca?: string, cert?: string, key?: string}|null
     */
    private static function resolveSsl(array $query): ?array
    {
        $sslmode = strtolower((string) ($query['sslmode'] ?? ''));
        $sslRaw  = strtolower((string) ($query['ssl'] ?? ''));

        $enabled = false;
        $rejectUnauthorized = true;

        if ($sslmode !== '') {
            if ($sslmode === 'disable') {
                return null;
            }
            $enabled = true;
            $rejectUnauthorized = in_array($sslmode, ['verify-ca', 'verify-full'], true);
        } elseif (in_array($sslRaw, ['true', '1', 'require'], true)) {
            $enabled = true;
            $rejectUnauthorized = false;
        } elseif (in_array($sslRaw, ['false', '0'], true)) {
            return null;
        }

        if (!$enabled) {
            return null;
        }

        $ssl = ['reject_unauthorized' => $rejectUnauthorized];
        $ca   = $query['ssl-ca'] ?? $query['sslrootcert'] ?? null;
        $cert = $query['sslcert'] ?? $query['ssl-cert'] ?? null;
        $key  = $query['sslkey'] ?? $query['ssl-key'] ?? null;
        if ($ca) {
            $ssl['ca'] = (string) $ca;
        }
        if ($cert) {
            $ssl['cert'] = (string) $cert;
        }
        if ($key) {
            $ssl['key'] = (string) $key;
        }

        return $ssl;
    }

    /**
     * Convert a connection URL into a Laravel connection config
     * usable in config('database.connections.x').
     *
     * @return array<string, mixed>
     */
    public static function toLaravelConfig(string $url): array
    {
        $d = self::parse($url);

        if ($d['driver'] === 'sqlite') {
            return [
                'driver'   => 'sqlite',
                'database' => $d['database'] ?: ':memory:',
                'prefix'   => '',
            ];
        }

        $cfg = [
            'driver'   => $d['driver'],
            'host'     => $d['host'] ?: '127.0.0.1',
            'port'     => $d['port'],
            'database' => $d['database'] ?? '',
            'username' => $d['username'] ?? '',
            'password' => $d['password'] ?? '',
            'charset'  => $d['driver'] === 'pgsql' ? 'utf8' : 'utf8mb4',
        ];

        $ssl = $d['ssl'];
        if ($ssl !== null) {
            if ($d['driver'] === 'pgsql') {
                $cfg['sslmode'] = $ssl['reject_unauthorized'] ? 'verify-full' : 'require';
                if (isset($ssl['ca'])) {
                    $cfg['sslrootcert'] = $ssl['ca'];
                }
                if (isset($ssl['cert'])) {
                    $cfg['sslcert'] = $ssl['cert'];
                }
                if (isset($ssl['key'])) {
                    $cfg['sslkey'] = $ssl['key'];
                }
            } elseif ($d['driver'] === 'sqlsrv') {
                $cfg['encrypt'] = true;
                $cfg['trust_server_certificate'] = !$ssl['reject_unauthorized'];
            } elseif ($d['driver'] === 'mysql' && defined('\PDO::MYSQL_ATTR_SSL_CA')) {
                $options = [];
                if (isset($ssl['ca'])) {
                    $options[\PDO::MYSQL_ATTR_SSL_CA] = $ssl['ca'];
                }
                if (isset($ssl['cert'])) {
                    $options[\PDO::MYSQL_ATTR_SSL_CERT] = $ssl['cert'];
                }
                if (isset($ssl['key'])) {
                    $options[\PDO::MYSQL_ATTR_SSL_KEY] = $ssl['key'];
                }
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $ssl['reject_unauthorized'];
                $cfg['options'] = $options;
            }
        }

        return $cfg;
    }
}
