<?php

namespace App\Storage\Telemetry;

use App\Contracts\Telemetry\LogWriterInterface;
use App\Models\TelemetryLog;
use Illuminate\Support\Str;

class RelationalLogWriter implements LogWriterInterface
{
    /**
     * @param  array<string, mixed>  $log
     */
    public function append(array $log): void
    {
        if (! isset($log['id'])) {
            $log['id'] = (string) Str::uuid();
        }

        TelemetryLog::query()->create($log);
    }

    /**
     * @param  array<int, array<string, mixed>>  $logs
     */
    public function appendBatch(array $logs): int
    {
        $count = 0;

        foreach ($logs as $log) {
            $this->append($log);
            $count++;
        }

        return $count;
    }
}
