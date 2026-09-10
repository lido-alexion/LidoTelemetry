<?php

namespace App\Contracts\Telemetry;

use App\Domain\Telemetry\AnalyticsQuery;

interface TelemetryQueryServiceInterface
{
    /**
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function search(AnalyticsQuery $query): array;

    /**
     * @return array<string, mixed>
     */
    public function aggregate(AnalyticsQuery $query): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function groupBy(AnalyticsQuery $query): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function timeSeries(AnalyticsQuery $query): array;

    /**
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function sessions(AnalyticsQuery $query): array;

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    public function funnels(array $steps, AnalyticsQuery $query): array;

    /**
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function views(AnalyticsQuery $query): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function journeys(AnalyticsQuery $query): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function retention(AnalyticsQuery $query): array;
}
