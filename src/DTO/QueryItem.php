<?php

namespace Blunx\AI\DTO;

/**
 * A single SQL query to execute, with its rendering hints.
 */
class QueryItem
{
    public function __construct(
        public readonly string $sql,
        public readonly string $uiHint = 'table',
        public readonly string $chartType = '',
    ) {}

    public function toArray(): array
    {
        return [
            'sql'        => $this->sql,
            'ui_hint'    => $this->uiHint,
            'chart_type' => $this->chartType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sql:      $data['sql'] ?? $data[0] ?? '',
            uiHint:   $data['ui_hint'] ?? $data['uiHint'] ?? 'table',
            chartType: $data['chart_type'] ?? $data['chartType'] ?? '',
        );
    }
}
