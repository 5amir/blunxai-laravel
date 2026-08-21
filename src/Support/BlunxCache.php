<?php

namespace Blunx\AI\Support;

use Illuminate\Support\Facades\DB;

/**
 * Small key/value cache persisted in the blunx_cache table.
 *
 * Used to store transient metadata such as the schema ID sent to the cloud.
 */
class BlunxCache
{
    /** Main connection (DSN-aware). */
    private static function conn()
    {
        return DB::connection(BlunxDatabase::mainConnection());
    }

    /**
     * Get a cached value, or null if missing or expired.
     */
    public static function get(string $key): ?string
    {
        $row = self::conn()->table('blunx_cache')->where('key', $key)->first();

        if (!$row) {
            return null;
        }

        if ($row->expiration && $row->expiration < time()) {
            self::conn()->table('blunx_cache')->where('key', $key)->delete();
            return null;
        }

        return $row->value;
    }

    /**
     * Store a value. $ttlSeconds null = permanent.
     */
    public static function put(string $key, ?string $value, ?int $ttlSeconds = null): void
    {
        self::conn()->table('blunx_cache')->updateOrInsert(
            ['key' => $key],
            [
                'value'      => $value,
                'expiration' => $ttlSeconds ? time() + $ttlSeconds : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Remove a key from the cache.
     */
    public static function forget(string $key): void
    {
        self::conn()->table('blunx_cache')->where('key', $key)->delete();
    }

    /**
     * Delete all expired entries.
     */
    public static function clearExpired(): void
    {
        self::conn()->table('blunx_cache')
            ->whereNotNull('expiration')
            ->where('expiration', '<', time())
            ->delete();
    }
}
