<?php

namespace App\Contracts\Telemetry;

interface TraceWriterInterface
{
    /**
     * @param  array<string, mixed>  $span
     */
    public function upsert(array $span): void;

    /**
     * @param  array<int, array<string, mixed>>  $spans
     */
    public function upsertBatch(array $spans): int;
}
