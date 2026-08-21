<?php

namespace Blunx\AI\Models\Concerns;

use Blunx\AI\Support\BlunxDatabase;

/**
 * Routes every Blunx model to the main database connection.
 *
 * The connection is either the `blunx` connection registered from
 * config('blunx.db_url') or the application's default connection.
 */
trait UsesBlunxConnection
{
    public function getConnectionName()
    {
        return BlunxDatabase::mainConnection();
    }
}
