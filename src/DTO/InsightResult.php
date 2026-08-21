<?php

namespace Blunx\AI\DTO;

/**
 * An AI-generated insight: type, severity, message and metadata.
 */
class InsightResult
{
    public string $type;
    public string $severity;
    public string $message;
    public array $meta;

    public function __construct(string $type, string $severity, string $message, array $meta = [])
    {
        $this->type = $type ?: 'info';
        $this->severity = $severity ?: 'info';
        $this->message = $message ?: '';
        $this->meta = $meta ?: [];
    }

    public function toArray(): array
    {
        return [
            'type'     => $this->type,
            'severity' => $this->severity,
            'message'  => $this->message,
            'meta'     => (object) $this->meta,
        ];
    }
}
