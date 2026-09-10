<?php

namespace App\Domain\Telemetry;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

readonly class EventEnvelope
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $eventId,
        public string $productId,
        public string $environment,
        public CarbonInterface $occurredAt,
        public CarbonInterface $receivedAt,
        public string $eventType,
        public ?string $category = null,
        public ?string $userId = null,
        public ?string $anonymousId = null,
        public ?string $sessionId = null,
        public ?string $viewInstanceId = null,
        public ?int $sequenceNumber = null,
        public ?string $correlationId = null,
        public ?string $traceId = null,
        public ?string $spanId = null,
        public ?array $metadata = null,
        public bool $clockSkewFlag = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (string) $data['event_id'],
            productId: (string) $data['product_id'],
            environment: (string) $data['environment'],
            occurredAt: self::parseTimestamp($data['occurred_at']),
            receivedAt: self::parseTimestamp($data['received_at']),
            eventType: (string) $data['event_type'],
            category: isset($data['category']) ? (string) $data['category'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            anonymousId: isset($data['anonymous_id']) ? (string) $data['anonymous_id'] : null,
            sessionId: isset($data['session_id']) ? (string) $data['session_id'] : null,
            viewInstanceId: isset($data['view_instance_id']) ? (string) $data['view_instance_id'] : null,
            sequenceNumber: isset($data['sequence_number']) ? (int) $data['sequence_number'] : null,
            correlationId: isset($data['correlation_id']) ? (string) $data['correlation_id'] : null,
            traceId: isset($data['trace_id']) ? (string) $data['trace_id'] : null,
            spanId: isset($data['span_id']) ? (string) $data['span_id'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
            clockSkewFlag: (bool) ($data['clock_skew_flag'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'product_id' => $this->productId,
            'environment' => $this->environment,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'received_at' => $this->receivedAt->toIso8601String(),
            'event_type' => $this->eventType,
            'category' => $this->category,
            'user_id' => $this->userId,
            'anonymous_id' => $this->anonymousId,
            'session_id' => $this->sessionId,
            'view_instance_id' => $this->viewInstanceId,
            'sequence_number' => $this->sequenceNumber,
            'correlation_id' => $this->correlationId,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'metadata' => $this->metadata,
            'clock_skew_flag' => $this->clockSkewFlag,
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
