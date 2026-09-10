<?php

namespace App\Services\Telemetry;

use App\Contracts\Telemetry\EventWriterInterface;
use App\Contracts\Telemetry\LogWriterInterface;
use App\Contracts\Telemetry\MetricWriterInterface;
use App\Contracts\Telemetry\TraceWriterInterface;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IngestionService
{
    public function __construct(
        protected PrivacyRedactionService $privacy,
        protected EventWriterInterface $eventWriter,
        protected MetricWriterInterface $metricWriter,
        protected LogWriterInterface $logWriter,
        protected TraceWriterInterface $traceWriter,
        protected MetadataCatalogService $metadataCatalog,
        protected SessionMaterializationService $sessionMaterialization,
        protected ViewMaterializationService $viewMaterialization,
        protected AggregateMaterializationService $aggregateMaterialization,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function ingestEvents(array $events, string $productId, string $environment): array
    {
        $this->assertBatchSize($events);

        $receivedAt = now();
        $prepared = [];
        $duplicates = 0;

        foreach ($events as $payload) {
            $event = $this->prepareEvent($payload, $productId, $environment, $receivedAt);

            if ($this->eventWriter->exists($event['event_id'])) {
                $duplicates++;

                continue;
            }

            $prepared[] = $event;
        }

        $result = $this->eventWriter->appendBatch($prepared);

        foreach ($prepared as $event) {
            $this->materializeEvent($event);
        }

        return [
            'accepted' => $result['inserted'],
            'duplicates' => $duplicates + $result['duplicates'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    public function ingestMetrics(array $metrics, string $productId, string $environment): array
    {
        $this->assertBatchSize($metrics);

        $receivedAt = now();
        $prepared = [];

        foreach ($metrics as $payload) {
            $prepared[] = $this->prepareMetric($payload, $productId, $environment, $receivedAt);
        }

        $inserted = $this->metricWriter->appendBatch($prepared);

        foreach ($prepared as $metric) {
            $this->aggregateMaterialization->materializeFromMetric($metric);
        }

        return ['accepted' => $inserted];
    }

    /**
     * @param  array<int, array<string, mixed>>  $logs
     * @return array<string, mixed>
     */
    public function ingestLogs(array $logs, string $productId, string $environment): array
    {
        $this->assertBatchSize($logs);

        $receivedAt = now();
        $prepared = [];

        foreach ($logs as $payload) {
            $prepared[] = $this->prepareLog($payload, $productId, $environment, $receivedAt);
        }

        $inserted = $this->logWriter->appendBatch($prepared);

        return ['accepted' => $inserted];
    }

    /**
     * @param  array<int, array<string, mixed>>  $spans
     * @return array<string, mixed>
     */
    public function ingestTraces(array $spans, string $productId, string $environment): array
    {
        $this->assertBatchSize($spans);

        $receivedAt = now();
        $prepared = [];

        foreach ($spans as $payload) {
            $prepared[] = $this->prepareSpan($payload, $productId, $environment, $receivedAt);
        }

        $upserted = $this->traceWriter->upsertBatch($prepared);

        return ['accepted' => $upserted];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function ingestOtelBatch(array $payload, string $productId, string $environment): array
    {
        $resourceSpans = $payload['resourceSpans'] ?? $payload['resource_spans'] ?? [];

        if (! is_array($resourceSpans)) {
            throw ValidationException::withMessages([
                'payload' => ['Invalid OpenTelemetry payload.'],
            ]);
        }

        $spans = [];

        foreach ($resourceSpans as $resourceSpan) {
            foreach ($resourceSpan['scopeSpans'] ?? $resourceSpan['scope_spans'] ?? [] as $scopeSpan) {
                foreach ($scopeSpan['spans'] ?? [] as $span) {
                    $spans[] = [
                        'trace_id' => $this->decodeOtelId($span['traceId'] ?? $span['trace_id'] ?? ''),
                        'span_id' => $this->decodeOtelId($span['spanId'] ?? $span['span_id'] ?? ''),
                        'parent_span_id' => $this->decodeOtelId($span['parentSpanId'] ?? $span['parent_span_id'] ?? '', allowEmpty: true),
                        'name' => $span['name'] ?? 'span',
                        'started_at' => $this->otelNanosToCarbon($span['startTimeUnixNano'] ?? $span['start_time_unix_nano'] ?? null),
                        'ended_at' => $this->otelNanosToCarbon($span['endTimeUnixNano'] ?? $span['end_time_unix_nano'] ?? null),
                        'status' => $span['status']['code'] ?? null,
                        'attributes' => $this->otelAttributesToMap($span['attributes'] ?? []),
                    ];
                }
            }
        }

        return $this->ingestTraces($spans, $productId, $environment);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function materializeEvent(array $event): void
    {
        $this->metadataCatalog->discoverFromEvent($event);
        $this->sessionMaterialization->materializeFromEvent($event);
        $this->viewMaterialization->materializeFromEvent($event);
        $this->aggregateMaterialization->materializeFromEvent($event);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareEvent(array $payload, string $productId, string $environment, Carbon $receivedAt): array
    {
        $eventId = (string) ($payload['event_id'] ?? Str::uuid());

        if ($eventId === '') {
            throw ValidationException::withMessages([
                'event_id' => ['Event ID is required.'],
            ]);
        }

        $metadata = $this->privacy->redact($payload['metadata'] ?? null);
        $occurredAt = $this->parseTimestamp($payload['occurred_at'] ?? null) ?? $receivedAt;

        return [
            'event_id' => $eventId,
            'product_id' => $productId,
            'environment' => $environment,
            'occurred_at' => $occurredAt,
            'received_at' => $receivedAt,
            'event_type' => (string) ($payload['event_type'] ?? 'unknown'),
            'category' => $payload['category'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'anonymous_id' => $payload['anonymous_id'] ?? null,
            'session_id' => $payload['session_id'] ?? null,
            'view_instance_id' => $payload['view_instance_id'] ?? null,
            'sequence_number' => isset($payload['sequence_number']) ? (int) $payload['sequence_number'] : null,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'trace_id' => $payload['trace_id'] ?? null,
            'span_id' => $payload['span_id'] ?? null,
            'metadata' => $metadata,
            'clock_skew_flag' => $this->detectClockSkew($occurredAt, $receivedAt),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareMetric(array $payload, string $productId, string $environment, Carbon $receivedAt): array
    {
        $occurredAt = $this->parseTimestamp($payload['occurred_at'] ?? null) ?? $receivedAt;

        return [
            'product_id' => $productId,
            'environment' => $environment,
            'occurred_at' => $occurredAt,
            'received_at' => $receivedAt,
            'name' => (string) ($payload['name'] ?? 'unknown'),
            'type' => (string) ($payload['type'] ?? 'gauge'),
            'value' => (float) ($payload['value'] ?? 0),
            'dimensions' => $this->privacy->redact($payload['dimensions'] ?? null),
            'user_id' => $payload['user_id'] ?? null,
            'session_id' => $payload['session_id'] ?? null,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'trace_id' => $payload['trace_id'] ?? null,
            'span_id' => $payload['span_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareLog(array $payload, string $productId, string $environment, Carbon $receivedAt): array
    {
        $occurredAt = $this->parseTimestamp($payload['occurred_at'] ?? null) ?? $receivedAt;
        $message = (string) ($payload['message'] ?? '');
        $maxString = (int) config('telemetry.ingestion.max_string_length', 1024);

        if (mb_strlen($message) > $maxString) {
            $message = mb_substr($message, 0, $maxString);
        }

        return [
            'product_id' => $productId,
            'environment' => $environment,
            'occurred_at' => $occurredAt,
            'received_at' => $receivedAt,
            'severity' => (string) ($payload['severity'] ?? 'info'),
            'service' => $payload['service'] ?? null,
            'message_code' => $payload['message_code'] ?? null,
            'message' => $message,
            'user_id' => $payload['user_id'] ?? null,
            'session_id' => $payload['session_id'] ?? null,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'trace_id' => $payload['trace_id'] ?? null,
            'span_id' => $payload['span_id'] ?? null,
            'metadata' => $this->privacy->redact($payload['metadata'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareSpan(array $payload, string $productId, string $environment, Carbon $receivedAt): array
    {
        $startedAt = $payload['started_at'] ?? null;
        $endedAt = $payload['ended_at'] ?? null;

        return [
            'product_id' => $productId,
            'environment' => $environment,
            'trace_id' => (string) ($payload['trace_id'] ?? ''),
            'span_id' => (string) ($payload['span_id'] ?? ''),
            'parent_span_id' => $payload['parent_span_id'] ?? null,
            'name' => (string) ($payload['name'] ?? 'span'),
            'started_at' => $startedAt instanceof Carbon ? $startedAt : ($this->parseTimestamp($startedAt) ?? $receivedAt),
            'ended_at' => $endedAt instanceof Carbon ? $endedAt : $this->parseTimestamp($endedAt),
            'duration_ms' => isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null,
            'status' => $payload['status'] ?? null,
            'attributes' => $this->privacy->redact($payload['attributes'] ?? null),
            'user_id' => $payload['user_id'] ?? null,
            'session_id' => $payload['session_id'] ?? null,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'received_at' => $receivedAt,
        ];
    }

    protected function detectClockSkew(Carbon $occurredAt, Carbon $receivedAt): bool
    {
        $threshold = (int) config('telemetry.ingestion.clock_skew_threshold_seconds', 300);

        return abs($occurredAt->diffInSeconds($receivedAt, false)) > $threshold;
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @param  array<int, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function otelAttributesToMap(array $attributes): array
    {
        $map = [];

        foreach ($attributes as $attribute) {
            $key = $attribute['key'] ?? null;

            if ($key === null) {
                continue;
            }

            $value = $attribute['value'] ?? [];
            $map[$key] = $value['stringValue']
                ?? $value['string_value']
                ?? $value['intValue']
                ?? $value['int_value']
                ?? $value['doubleValue']
                ?? $value['double_value']
                ?? $value['boolValue']
                ?? $value['bool_value']
                ?? null;
        }

        return $map;
    }

    protected function otelNanosToCarbon(mixed $nanos): ?Carbon
    {
        if ($nanos === null || $nanos === '') {
            return null;
        }

        return Carbon::createFromTimestampMs((int) floor(((int) $nanos) / 1_000_000));
    }

    protected function decodeOtelId(string $value, bool $allowEmpty = false): ?string
    {
        if ($value === '') {
            return $allowEmpty ? null : '';
        }

        if (ctype_xdigit($value) && strlen($value) % 2 === 0) {
            return strtolower($value);
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $batch
     */
    protected function assertBatchSize(array $batch): void
    {
        $max = (int) config('telemetry.ingestion.max_batch_size', 500);

        if (count($batch) > $max) {
            throw ValidationException::withMessages([
                'batch' => ["Batch size exceeds maximum of {$max}."],
            ]);
        }
    }
}
