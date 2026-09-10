<?php

namespace App\Domain\Telemetry;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

readonly class AnalyticsQuery
{
    public const SIGNAL_EVENTS = 'events';

    public const SIGNAL_METRICS = 'metrics';

    public const SIGNAL_LOGS = 'logs';

    public const SIGNAL_TRACES = 'traces';

    /**
     * @param  list<string>  $productIds
     * @param  list<string>  $environmentKeys
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $groupBy
     * @param  list<array<string, mixed>>  $aggregations
     * @param  list<array<string, mixed>>  $metadataPathPredicates
     * @param  list<array<string, string>>  $orderBy
     */
    public function __construct(
        public array $productIds,
        public array $environmentKeys = [],
        public string $signalFamily = self::SIGNAL_EVENTS,
        public ?CarbonInterface $timeRangeStart = null,
        public ?CarbonInterface $timeRangeEnd = null,
        public array $filters = [],
        public array $groupBy = [],
        public array $aggregations = [],
        public array $metadataPathPredicates = [],
        public array $orderBy = [],
        public int $limit = 100,
        public int $offset = 0,
        public ?string $timeBucket = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $timeRange = $data['time_range'] ?? $data['timeRange'] ?? null;

        return new self(
            productIds: array_values(array_map('strval', $data['product_ids'] ?? $data['productIds'] ?? [])),
            environmentKeys: array_values(array_map('strval', $data['environment_keys'] ?? $data['environmentKeys'] ?? [])),
            signalFamily: (string) ($data['signal_family'] ?? $data['signalFamily'] ?? self::SIGNAL_EVENTS),
            timeRangeStart: self::parseOptionalTimestamp(is_array($timeRange) ? ($timeRange['start'] ?? null) : ($data['time_range_start'] ?? null)),
            timeRangeEnd: self::parseOptionalTimestamp(is_array($timeRange) ? ($timeRange['end'] ?? null) : ($data['time_range_end'] ?? null)),
            filters: array_values($data['filters'] ?? []),
            groupBy: array_values(array_map('strval', $data['group_by'] ?? $data['groupBy'] ?? [])),
            aggregations: array_values($data['aggregations'] ?? []),
            metadataPathPredicates: array_values($data['metadata_path_predicates'] ?? $data['metadataPathPredicates'] ?? []),
            orderBy: array_values($data['order_by'] ?? $data['orderBy'] ?? []),
            limit: (int) ($data['limit'] ?? config('telemetry.query.default_limit', 100)),
            offset: (int) ($data['offset'] ?? 0),
            timeBucket: isset($data['time_bucket']) || isset($data['timeBucket'])
                ? (string) ($data['time_bucket'] ?? $data['timeBucket'])
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_ids' => $this->productIds,
            'environment_keys' => $this->environmentKeys,
            'signal_family' => $this->signalFamily,
            'time_range' => [
                'start' => $this->timeRangeStart?->toIso8601String(),
                'end' => $this->timeRangeEnd?->toIso8601String(),
            ],
            'filters' => $this->filters,
            'group_by' => $this->groupBy,
            'aggregations' => $this->aggregations,
            'metadata_path_predicates' => $this->metadataPathPredicates,
            'order_by' => $this->orderBy,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'time_bucket' => $this->timeBucket,
        ];
    }

    public function cappedLimit(): int
    {
        $maxLimit = (int) config('telemetry.query.max_limit', 1000);

        return min(max($this->limit, 1), $maxLimit);
    }

    private static function parseOptionalTimestamp(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
