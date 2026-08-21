<?php
namespace Blunx\AI;

use Exception;

/**
 * Exception raised by the Blunx API client.
 *
 * Carries the HTTP status code returned by the server so callers can map
 * it to a user-facing message.
 */
class LLMException extends Exception
{
    public function __construct(
        string $message,
        public int $statusCode = 0
    ) {
        parent::__construct($message);
    }
}
