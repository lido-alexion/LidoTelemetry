<?php

namespace App\Contracts\Telemetry;

interface LogWriterInterface
{
    /**
     * @param  array<string, mixed>  $log
     */
    public function append(array $log): void;

    /**
     * @param  array<int, array<string, mixed>>  $logs
     */
    public function appendBatch(array $logs): int;
}
