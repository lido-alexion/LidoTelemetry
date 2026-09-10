<?php

declare(strict_types=1);

namespace LidoAlexion\Telemetry;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Async PHP SDK client for Lido Telemetry ingestion.
 *
 * Business requests never block on network delivery — payloads are queued
 * durably and flushed by a background worker or shutdown hook.
 */
class TelemetryClient
{
    private string $endpoint;

    private string $ingestionToken;

    private EventQueue $queue;

    private int $batchSize;

    private ?string $userId = null;

    private ?string $sessionId = null;

    private ?string $correlationId = null;

    /** @var array<string, mixed> */
    private array $applicationContext = [];

    private bool $flushRegistered = false;

    /** @var callable|null */
    private $onDiagnostic = null;

    private ?string $lastDeliveryError = null;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(array $options)
    {
        $endpoint = (string) ($options['endpoint'] ?? '');
        $token = (string) ($options['ingestion_token'] ?? $options['ingestionToken'] ?? '');

        if ($endpoint === '' || $token === '') {
            throw new RuntimeException('endpoint and ingestion_token are required.');
        }

        $this->endpoint = rtrim($endpoint, '/');
        $this->ingestionToken = $token;
        $this->batchSize = max(1, (int) ($options['batch_size'] ?? $options['batchSize'] ?? 50));
        $this->queue = new EventQueue($options['queue'] ?? []);
        $this->applicationContext = is_array($options['application_context'] ?? null)
            ? $options['application_context']
            : [];

        if (isset($options['on_diagnostic']) && is_callable($options['on_diagnostic'])) {
            $this->onDiagnostic = $options['on_diagnostic'];
        }

        $this->registerShutdownFlush();
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function setApplicationContext(array $context): void
    {
        $this->applicationContext = $context;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function trackEvent(string $eventType, array $metadata = [], ?string $eventId = null): void
    {
        $category = str_contains($eventType, '.') ? explode('.', $eventType, 2)[0] : null;

        $this->enqueue('events', [
            'event_id' => $eventId ?? $this->generateId(),
            'event_type' => $eventType,
            'occurred_at' => $this->now(),
            'category' => $category,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'metadata' => $this->mergeMetadata($metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dimensions
     */
    public function trackMetric(
        string $name,
        float $value,
        string $type = 'gauge',
        array $dimensions = [],
    ): void {
        $this->enqueue('metrics', [
            'name' => $name,
            'type' => $type,
            'value' => $value,
            'occurred_at' => $this->now(),
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'dimensions' => $this->mergeMetadata($dimensions),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function trackLog(
        string $severity,
        string $message,
        ?string $service = null,
        ?string $messageCode = null,
        array $metadata = [],
    ): void {
        $this->enqueue('logs', [
            'severity' => $severity,
            'message' => $message,
            'service' => $service,
            'message_code' => $messageCode,
            'occurred_at' => $this->now(),
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'metadata' => $this->mergeMetadata($metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function trackSpan(
        string $traceId,
        string $spanId,
        string $name,
        ?string $parentSpanId = null,
        ?string $startedAt = null,
        ?string $endedAt = null,
        ?int $durationMs = null,
        ?string $status = null,
        array $attributes = [],
    ): void {
        $this->enqueue('traces', [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'name' => $name,
            'started_at' => $startedAt ?? $this->now(),
            'ended_at' => $endedAt,
            'duration_ms' => $durationMs,
            'status' => $status,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'attributes' => $this->mergeMetadata($attributes),
        ]);
    }

    /**
     * Flush queued payloads to the ingestion API.
     *
     * @return array{delivered: int, failed: int}
     */
    public function flush(): array
    {
        $delivered = 0;
        $failed = 0;

        while (true) {
            $batch = $this->queue->pop($this->batchSize);

            if ($batch === []) {
                break;
            }

            $byKind = [
                'events' => [],
                'metrics' => [],
                'logs' => [],
                'traces' => [],
            ];

            $queueIds = [];

            foreach ($batch as $item) {
                $kind = (string) ($item['kind'] ?? 'events');
                $payload = $item['payload'] ?? null;

                if (! is_array($payload)) {
                    continue;
                }

                $byKind[$kind][] = $payload;

                if (isset($item['queue_id'])) {
                    $queueIds[] = (string) $item['queue_id'];
                }
            }

            $success = true;

            foreach ($byKind as $kind => $payloads) {
                if ($payloads === []) {
                    continue;
                }

                try {
                    $this->deliver($kind, $payloads);
                    $delivered += count($payloads);
                } catch (Throwable $exception) {
                    $success = false;
                    $failed += count($payloads);
                    $this->lastDeliveryError = $exception->getMessage();
                    $this->diagnostic('delivery failed', $exception);

                    foreach ($batch as $item) {
                        if (! is_array($item['payload'] ?? null)) {
                            continue;
                        }

                        $this->queue->push($item);
                    }

                    break;
                }
            }

            if ($success && $queueIds !== []) {
                $this->queue->acknowledge($queueIds);
            }

            if (! $success) {
                break;
            }
        }

        return ['delivered' => $delivered, 'failed' => $failed];
    }

    public function getQueueDepth(): int
    {
        return $this->queue->count();
    }

    public function getLastDeliveryError(): ?string
    {
        return $this->lastDeliveryError;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enqueue(string $kind, array $payload): void
    {
        $this->queue->push([
            'queue_id' => $this->generateId(),
            'kind' => $kind,
            'payload' => $payload,
            'enqueued_at' => $this->now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function deliver(string $kind, array $payloads): void
    {
        $path = match ($kind) {
            'events' => '/ingest/events',
            'metrics' => '/ingest/metrics',
            'logs' => '/ingest/logs',
            'traces' => '/ingest/traces',
            default => throw new RuntimeException("Unknown telemetry kind: {$kind}"),
        };

        $bodyKey = match ($kind) {
            'events' => 'events',
            'metrics' => 'metrics',
            'logs' => 'logs',
            'traces' => 'spans',
            default => 'payload',
        };

        $url = $this->endpoint.$path;
        $body = json_encode([$bodyKey => $payloads], JSON_THROW_ON_ERROR);

        if (function_exists('curl_init')) {
            $this->deliverWithCurl($url, $body);

            return;
        }

        $this->deliverWithStream($url, $body);
    }

    private function deliverWithCurl(string $url, string $body): void
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$this->ingestionToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error !== '' ? $error : "HTTP {$status}: {$response}");
        }
    }

    private function deliverWithStream(string $url, string $body): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer '.$this->ingestionToken,
                ]),
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        $status = 0;

        if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $matches)) {
            $status = (int) $matches[0];
        }

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException("HTTP {$status}: ".($response ?: 'request failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $metadata): array
    {
        return array_merge($this->applicationContext, $metadata);
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format(DATE_ATOM);
    }

    private function generateId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function registerShutdownFlush(): void
    {
        if ($this->flushRegistered) {
            return;
        }

        $this->flushRegistered = true;

        register_shutdown_function(function (): void {
            try {
                $this->flush();
            } catch (Throwable $exception) {
                $this->diagnostic('shutdown flush failed', $exception);
            }
        });
    }

    private function diagnostic(string $message, mixed $detail = null): void
    {
        if ($this->onDiagnostic !== null) {
            ($this->onDiagnostic)($message, $detail);
        }
    }
}
