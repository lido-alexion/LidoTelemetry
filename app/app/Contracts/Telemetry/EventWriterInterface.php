<?php

namespace App\Contracts\Telemetry;

interface EventWriterInterface
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function append(array $event): bool;

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{inserted: int, duplicates: int}
     */
    public function appendBatch(array $events): array;

    public function exists(string $eventId): bool;
}
