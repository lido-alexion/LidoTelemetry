<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryDailyAggregate;
use App\Models\TelemetryHourlyAggregate;
use Carbon\Carbon;

class AggregateMaterializationService
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function materializeFromEvent(array $event): void
    {
        $occurredAt = $event['occurred_at'] instanceof Carbon
            ? $event['occurred_at']
            : Carbon::parse($event['occurred_at']);

        $dimensions = [
            'event_type' => $event['event_type'] ?? 'unknown',
            'category' => $event['category'] ?? null,
        ];

        $this->incrementAggregate(
            productId: $event['product_id'],
            environment: $event['environment'],
            signalFamily: 'events',
            metricKey: (string) ($event['event_type'] ?? 'unknown'),
            occurredAt: $occurredAt,
            dimensions: $dimensions,
        );
    }

    /**
     * @param  array<string, mixed>  $metric
     */
    public function materializeFromMetric(array $metric): void
    {
        $occurredAt = $metric['occurred_at'] instanceof Carbon
            ? $metric['occurred_at']
            : Carbon::parse($metric['occurred_at']);

        $dimensions = array_merge(
            ['type' => $metric['type'] ?? 'gauge'],
            is_array($metric['dimensions'] ?? null) ? $metric['dimensions'] : [],
        );

        $this->incrementAggregate(
            productId: $metric['product_id'],
            environment: $metric['environment'],
            signalFamily: 'metrics',
            metricKey: (string) ($metric['name'] ?? 'unknown'),
            occurredAt: $occurredAt,
            dimensions: $dimensions,
            sumValue: (float) ($metric['value'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $dimensions
     */
    public function incrementAggregate(
        string $productId,
        string $environment,
        string $signalFamily,
        string $metricKey,
        Carbon $occurredAt,
        array $dimensions = [],
        ?float $sumValue = null,
    ): void {
        $dimensionsHash = $this->dimensionsHash($dimensions);
        $hourStart = $occurredAt->copy()->startOfHour();
        $bucketDate = $occurredAt->toDateString();

        $this->upsertHourly($productId, $environment, $signalFamily, $metricKey, $hourStart, $dimensions, $dimensionsHash, $sumValue);
        $this->upsertDaily($productId, $environment, $signalFamily, $metricKey, $bucketDate, $dimensions, $dimensionsHash, $sumValue);
    }

    /**
     * @param  array<string, mixed>  $dimensions
     */
    protected function upsertHourly(
        string $productId,
        string $environment,
        string $signalFamily,
        string $metricKey,
        Carbon $bucketStart,
        array $dimensions,
        string $dimensionsHash,
        ?float $sumValue,
    ): void {
        $aggregate = TelemetryHourlyAggregate::query()->firstOrNew([
            'product_id' => $productId,
            'environment' => $environment,
            'signal_family' => $signalFamily,
            'metric_key' => $metricKey,
            'bucket_start' => $bucketStart,
            'dimensions_hash' => $dimensionsHash,
        ]);

        $aggregate->dimensions = $dimensions;
        $aggregate->count = ($aggregate->count ?? 0) + 1;

        if ($sumValue !== null) {
            $aggregate->sum_value = ($aggregate->sum_value ?? 0) + $sumValue;
        }

        $aggregate->save();
    }

    /**
     * @param  array<string, mixed>  $dimensions
     */
    protected function upsertDaily(
        string $productId,
        string $environment,
        string $signalFamily,
        string $metricKey,
        string $bucketDate,
        array $dimensions,
        string $dimensionsHash,
        ?float $sumValue,
    ): void {
        $aggregate = TelemetryDailyAggregate::query()->firstOrNew([
            'product_id' => $productId,
            'environment' => $environment,
            'signal_family' => $signalFamily,
            'metric_key' => $metricKey,
            'bucket_date' => $bucketDate,
            'dimensions_hash' => $dimensionsHash,
        ]);

        $aggregate->dimensions = $dimensions;
        $aggregate->count = ($aggregate->count ?? 0) + 1;

        if ($sumValue !== null) {
            $aggregate->sum_value = ($aggregate->sum_value ?? 0) + $sumValue;
        }

        $aggregate->save();
    }

    /**
     * @param  array<string, mixed>  $dimensions
     */
    protected function dimensionsHash(array $dimensions): string
    {
        ksort($dimensions);

        return hash('sha256', json_encode($dimensions, JSON_THROW_ON_ERROR));
    }

    /**
     * Refresh aggregates for a time window from raw events (rebuild helper).
     */
    public function refreshWindow(string $productId, string $environment, Carbon $from, Carbon $to): void
    {
        // Intentionally lightweight in V7: incremental updates happen on ingest.
        // Batch rebuild can be extended to scan canonical tables when needed.
    }
}
