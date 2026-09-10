<?php

namespace App\Contracts\Telemetry;

interface MetricWriterInterface
{
    /**
     * @param  array<string, mixed>  $metric
     */
    public function append(array $metric): void;

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     */
    public function appendBatch(array $metrics): int;
}
