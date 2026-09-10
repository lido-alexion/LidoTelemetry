<?php

namespace App\Storage\Telemetry;

use App\Contracts\Telemetry\TraceWriterInterface;
use App\Models\TelemetryTraceSpan;
use Illuminate\Support\Str;

class RelationalTraceWriter implements TraceWriterInterface
{
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
        $count = 0;

        foreach ($spans as $span) {
            $this->upsert($span);
            $count++;
        }

        return $count;
    }
}
