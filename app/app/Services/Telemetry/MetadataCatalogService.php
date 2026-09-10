<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryMetadataKey;
use Carbon\Carbon;

class MetadataCatalogService
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function discoverFromEvent(array $event): void
    {
        $metadata = $event['metadata'] ?? null;

        if (! is_array($metadata) || $metadata === []) {
            return;
        }

        $productId = $event['product_id'];
        $occurredAt = $event['occurred_at'] instanceof Carbon
            ? $event['occurred_at']
            : Carbon::parse($event['occurred_at']);

        $this->walkMetadata($productId, $metadata, $occurredAt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function walkMetadata(string $productId, array $metadata, Carbon $occurredAt, string $prefix = ''): void
    {
        foreach ($metadata as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $this->walkMetadata($productId, $value, $occurredAt, $fullKey);

                continue;
            }

            $this->upsertKey($productId, $fullKey, $value, $occurredAt);
        }
    }

    protected function upsertKey(string $productId, string $key, mixed $value, Carbon $occurredAt): void
    {
        $observedType = $this->inferType($value);
        $threshold = (int) config('telemetry.ingestion.high_cardinality_threshold', 1000);

        $existing = TelemetryMetadataKey::query()
            ->where('product_id', $productId)
            ->where('metadata_key', $key)
            ->first();

        if ($existing === null) {
            TelemetryMetadataKey::query()->create([
                'product_id' => $productId,
                'metadata_key' => $key,
                'observed_type' => $observedType,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
                'approx_cardinality' => 1,
                'high_cardinality' => false,
            ]);

            return;
        }

        $cardinality = $existing->approx_cardinality + 1;

        $existing->fill([
            'observed_type' => $this->mergeTypes($existing->observed_type, $observedType),
            'last_seen_at' => $occurredAt->greaterThan($existing->last_seen_at) ? $occurredAt : $existing->last_seen_at,
            'approx_cardinality' => $cardinality,
            'high_cardinality' => $cardinality >= $threshold,
        ]);
        $existing->save();
    }

    protected function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_string($value) => 'string',
            default => 'mixed',
        };
    }

    protected function mergeTypes(string $existing, string $incoming): string
    {
        if ($existing === $incoming) {
            return $existing;
        }

        return 'mixed';
    }
}
