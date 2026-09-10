<?php

namespace App\Domain\Telemetry;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

readonly class LogEntry
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $productId,
        public string $environment,
        public CarbonInterface $occurredAt,
        public CarbonInterface $receivedAt,
        public string $severity,
        public string $message,
        public ?string $service = null,
        public ?string $messageCode = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        public ?string $correlationId = null,
        public ?string $traceId = null,
        public ?string $spanId = null,
        public ?array $metadata = null,
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
            occurredAt: self::parseTimestamp($data['occurred_at']),
            receivedAt: self::parseTimestamp($data['received_at']),
            severity: (string) $data['severity'],
            message: (string) $data['message'],
            service: isset($data['service']) ? (string) $data['service'] : null,
            messageCode: isset($data['message_code']) ? (string) $data['message_code'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            sessionId: isset($data['session_id']) ? (string) $data['session_id'] : null,
            correlationId: isset($data['correlation_id']) ? (string) $data['correlation_id'] : null,
            traceId: isset($data['trace_id']) ? (string) $data['trace_id'] : null,
            spanId: isset($data['span_id']) ? (string) $data['span_id'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
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
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'received_at' => $this->receivedAt->toIso8601String(),
            'severity' => $this->severity,
            'service' => $this->service,
            'message_code' => $this->messageCode,
            'message' => $this->message,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'correlation_id' => $this->correlationId,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'metadata' => $this->metadata,
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
