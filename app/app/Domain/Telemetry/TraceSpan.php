<?php

namespace App\Domain\Telemetry;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

readonly class TraceSpan
{
    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public function __construct(
        public string $id,
        public string $productId,
        public string $environment,
        public string $traceId,
        public string $spanId,
        public string $name,
        public CarbonInterface $startedAt,
        public CarbonInterface $receivedAt,
        public ?string $parentSpanId = null,
        public ?CarbonInterface $endedAt = null,
        public ?int $durationMs = null,
        public ?string $status = null,
        public ?array $attributes = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        public ?string $correlationId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            productId: (string) $data['product_id'],
            environment: (string) $data['environment'],
            traceId: (string) $data['trace_id'],
            spanId: (string) $data['span_id'],
            name: (string) $data['name'],
            startedAt: self::parseTimestamp($data['started_at']),
            receivedAt: self::parseTimestamp($data['received_at']),
            parentSpanId: isset($data['parent_span_id']) ? (string) $data['parent_span_id'] : null,
            endedAt: isset($data['ended_at']) ? self::parseTimestamp($data['ended_at']) : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            attributes: isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            sessionId: isset($data['session_id']) ? (string) $data['session_id'] : null,
            correlationId: isset($data['correlation_id']) ? (string) $data['correlation_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'environment' => $this->environment,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'name' => $this->name,
            'started_at' => $this->startedAt->toIso8601String(),
            'ended_at' => $this->endedAt?->toIso8601String(),
            'duration_ms' => $this->durationMs,
            'status' => $this->status,
            'attributes' => $this->attributes,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'received_at' => $this->receivedAt->toIso8601String(),
        ];
    }

    private static function parseTimestamp(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
