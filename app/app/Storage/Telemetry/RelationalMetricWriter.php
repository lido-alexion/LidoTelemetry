<?php

namespace App\Storage\Telemetry;

use App\Contracts\Telemetry\MetricWriterInterface;
use App\Models\TelemetryMetric;
use Illuminate\Support\Str;

class RelationalMetricWriter implements MetricWriterInterface
{
    /**
     * @param  array<string, mixed>  $metric
     */
    public function append(array $metric): void
    {
        if (! isset($metric['id'])) {
            $metric['id'] = (string) Str::uuid();
        }

        TelemetryMetric::query()->create($metric);
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     */
    public function appendBatch(array $metrics): int
    {
        $count = 0;

        foreach ($metrics as $metric) {
            $this->append($metric);
            $count++;
        }

        return $count;
    }
}
