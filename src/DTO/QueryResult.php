<?php

namespace Blunx\AI\DTO;

/**
 * A collection of queries to execute, with optional warnings.
 *
 * Accepts several input shapes for backwards compatibility: bare SQL strings,
 * associative arrays (sql/ui_hint/chart_type) or QueryItem instances.
 */
class QueryResult
{
    /** @var QueryItem[] */
    public readonly array $queries;

    public readonly array $warnings;

    public function __construct(
        array $queries = [],
        array $warnings = [],
        array $uiHints = [],
    ) {
        // Support the legacy parallel-arrays format during the transition.
        if (!empty($uiHints) && !empty($queries) && !($queries[0] instanceof QueryItem)) {
            $items = [];
            foreach ($queries as $i => $sql) {
                $items[] = new QueryItem(
                    sql:      $sql,
                    uiHint:   $uiHints[$i] ?? 'table',
                    chartType: '',
                );
            }
            $this->queries = $items;
        } elseif (!empty($queries) && is_array($queries[0]) && isset($queries[0]['sql'])) {
            // New format: associative array with sql/ui_hint/chart_type
            $this->queries = array_map(fn(array $q) => QueryItem::fromArray($q), $queries);
        } elseif (!empty($queries) && $queries[0] instanceof QueryItem) {
            $this->queries = $queries;
        } else {
            // Legacy: bare strings
            $this->queries = array_map(fn(string $sql) => new QueryItem(sql: $sql), $queries);
        }

        $this->warnings = $warnings;
    }

    public function toArray(): array
    {
        return [
            'queries'  => array_map(fn(QueryItem $q) => $q->toArray(), $this->queries),
            'warnings' => $this->warnings,
        ];
    }
}