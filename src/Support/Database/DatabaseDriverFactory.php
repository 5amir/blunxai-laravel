<?php

namespace Blunx\AI\Support\Database;

use InvalidArgumentException;

/**
 * Resolves the database driver to use for schema scanning.
 *
 * The driver is derived from the BLUNX_DB_URL scheme when a DSN is provided,
 * otherwise from the configured BLUNX_DB_CONNECTION connection.
 */
class DatabaseDriverFactory
{
    /**
     * Create a database driver instance based on the connection.
     */
    public static function make(?string $driver = null): DatabaseDriverInterface
    {
        // When a DSN is provided, the driver is derived from the URL scheme
        // (mysql:// or mariadb:// → mysql, postgres:// → pgsql, sqlite:// → sqlite…).
        $dbUrl = env('BLUNX_DB_URL', '');
        if ($dbUrl !== '') {
            $parsed = DsnParser::parse($dbUrl);
            $driverName = $parsed['driver'];

            return self::fromDriverName($driverName);
        }

        // Fall back to the configured connection.
        $driver = $driver ?? env('BLUNX_DB_CONNECTION', 'mysql');

        $connection = config("database.connections.{$driver}");

        if (!$connection) {
            throw new InvalidArgumentException("Database connection '{$driver}' not found.");
        }

        $driverName = $connection['driver'] ?? 'mysql';

        return self::fromDriverName($driverName);
    }

    /**
     * Instantiate the database driver from a normalized driver name.
     */
    private static function fromDriverName(string $driverName): DatabaseDriverInterface
    {
        return match ($driverName) {
            'pgsql', 'postgresql'  => new PostgreSqlDriver(),
            'mysql', 'mariadb'     => new MySqlDriver(),
            'sqlite'               => new SQLiteDriver(),
            'sqlsrv', 'sqlserver'  => new SqlServerDriver(),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driverName}"),
        };
    }

    /**
     * Create a database driver instance from environment variable
     */
    public static function fromEnv(): DatabaseDriverInterface
    {
        // Get the driver from the BLUNX_DB_CONNECTION environment variable.
        $driver = env('BLUNX_DB_CONNECTION', 'mysql');
        
        return self::make($driver);
    }
}