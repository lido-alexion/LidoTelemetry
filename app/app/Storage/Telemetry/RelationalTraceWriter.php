<?php

namespace App\Storage\Telemetry;

use App\Contracts\Telemetry\TraceWriterInterface;
use App\Models\TelemetryTraceSpan;
use Illuminate\Support\Str;

class RelationalTraceWriter implements TraceWriterInterface
{
    /**
     * Keep each statement below SQLite's conservative 999 bind parameter limit.
     * Trace rows currently use 19 columns, so 50 rows require at most 950 binds.
     */
    private const UPSERT_CHUNK_SIZE = 50;

    /**
     * @param  array<string, mixed>  $span
     */
    public function upsert(array $span): void
    {
        if (! isset($span['id'])) {
            $span['id'] = (string) Str::uuid();
        }

        TelemetryTraceSpan::query()->updateOrCreate(
            [
                'product_id' => $span['product_id'],
                'environment' => $span['environment'],
                'trace_id' => $span['trace_id'],
                'span_id' => $span['span_id'],
            ],
            $span,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $spans
     */
    public function upsertBatch(array $spans): int
    {
        if ($spans === []) {
            return 0;
        }

        foreach ($spans as &$span) {
            if (! isset($span['id'])) {
                $span['id'] = (string) Str::uuid();
            }

            // Query-builder upserts bypass model attribute casts.
            if (isset($span['attributes']) && (is_array($span['attributes']) || is_object($span['attributes']))) {
                $span['attributes'] = json_encode($span['attributes'], JSON_THROW_ON_ERROR);
            }
        }
        unset($span);

        $columns = array_keys($spans[0]);
        $updatedColumns = array_values(array_diff($columns, ['created_at']));

        foreach (array_chunk($spans, self::UPSERT_CHUNK_SIZE) as $chunk) {
            TelemetryTraceSpan::query()->upsert(
                $chunk,
                ['product_id', 'environment', 'trace_id', 'span_id'],
                $updatedColumns,
            );
        }

        return count($spans);
    }
}
