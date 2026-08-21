<?php

namespace Blunx\AI\Support;

/**
 * Consumes a Server-Sent Events stream from the Blunx cloud.
 *
 * Transport-only: it depends only on its parameters (url, payload, callbacks)
 * and returns null on success, otherwise the error string.
 */
class SseClient
{
    /**
     * POST to a stream endpoint and dispatch the received events.
     *
     * @param  string   $url       Full URL (e.g. http://hub/api/v1/chat/queries).
     * @param  array    $payload   JSON body.
     * @param  string   $apiKey    Application key (X-Blunx-Key header).
     * @param  string   $llmApiKey LLM key (X-Blunx-LLM-Key header).
     * @param  callable $onStep    fn(array $d) → `step` event.
     * @param  callable $onResult  fn(array $d) → `result` event.
     * @return string|null null on success, otherwise the error message.
     */
    public static function consume(
        string   $url,
        array    $payload,
        string   $apiKey,
        string   $llmApiKey,
        callable $onStep,
        callable $onResult,
    ): ?string {
        $buffer   = '';
        $errorMsg = null;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'X-Blunx-Key: ' . $apiKey,
                'X-Blunx-LLM-Key: ' . $llmApiKey,
            ],
            CURLOPT_TIMEOUT        => 150,
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, &$errorMsg, $onStep, $onResult) {
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $raw    = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    if (empty(trim($raw))) continue;

                    $event    = null;
                    $jsonData = null;
                    foreach (explode("\n", $raw) as $line) {
                        if (str_starts_with($line, 'event:')) {
                            $event = trim(substr($line, 6));
                        } elseif (str_starts_with($line, 'data:')) {
                            $jsonData = json_decode(trim(substr($line, 5)), true);
                        }
                    }

                    if (!$event || !$jsonData) continue;

                    match ($event) {
                        'step'   => $onStep($jsonData),
                        'result' => $onResult($jsonData),
                        'error'  => ($errorMsg = $jsonData['message'] ?? 'Server error'),
                        default  => null,
                    };
                }

                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) return 'cURL error: ' . $curlError;
        if ($httpCode >= 400) {
            $serverError = $errorMsg ?? trim($buffer);
            if (empty($serverError) || $serverError === $buffer) {
                $decoded = json_decode($buffer, true);
                $serverError = $decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? ('Blunx server error (HTTP ' . $httpCode . ')');
            }
            return $serverError;
        }

        return $errorMsg;
    }
}
