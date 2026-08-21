<?php

namespace Blunx\AI\DTO;

/**
 * Result of executing a set of SQL queries.
 *
 * Holds the raw results, non-blocking warnings (blocked or failed queries)
 * and per-query metadata used for rendering.
 */
class ExecutionResult
{
    public array $results;
    public array $warnings;
    public array $meta;

    public function __construct(array $results, array $warnings = [], array $meta = [])
    {
        $this->results = $results;
        $this->warnings = $warnings;
        $this->meta = $meta;
    }

    public function toArray(): array
    {
        return [
            'results'  => $this->results,
            'warnings' => $this->warnings,
            'meta'     => (object) $this->meta,
        ];
    }
}
