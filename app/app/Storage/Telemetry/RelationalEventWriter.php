<?php

namespace App\Storage\Telemetry;

use App\Contracts\Telemetry\EventWriterInterface;
use App\Models\TelemetryEvent;

class RelationalEventWriter implements EventWriterInterface
{
    public function exists(string $eventId): bool
    {
        return TelemetryEvent::query()->where('event_id', $eventId)->exists();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function append(array $event): bool
    {
        if ($this->exists($event['event_id'])) {
            return false;
        }

        TelemetryEvent::query()->create($event);

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{inserted: int, duplicates: int}
     */
    public function appendBatch(array $events): array
    {
        $inserted = 0;
        $duplicates = 0;

        foreach ($events as $event) {
            if ($this->append($event)) {
                $inserted++;
            } else {
                $duplicates++;
            }
        }

        return ['inserted' => $inserted, 'duplicates' => $duplicates];
    }
}
