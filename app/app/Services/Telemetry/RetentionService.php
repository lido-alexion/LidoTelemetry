<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryDailyAggregate;
use App\Models\TelemetryEvent;
use App\Models\TelemetryHourlyAggregate;
use App\Models\TelemetryLog;
use App\Models\TelemetryMetric;
use App\Models\TelemetryProductEnvironment;
use App\Models\TelemetryTraceSpan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RetentionService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @return array<string, int>
     */
    public function apply(?int $actorUserId = null): array
    {
        $counts = [
            'raw_deleted' => 0,
            'hourly_deleted' => 0,
            'daily_deleted' => 0,
        ];

        $defaultRawDays = (int) config('telemetry.retention.raw_days', 90);
        $defaultAggregateDays = (int) config('telemetry.retention.aggregate_days', 730);

        $environments = TelemetryProductEnvironment::query()->get();

        foreach ($environments as $environment) {
            $rawDays = $environment->raw_retention_days ?? $defaultRawDays;
            $aggregateDays = $environment->aggregate_retention_days ?? $defaultAggregateDays;

            $rawCutoff = now()->subDays($rawDays);
            $aggregateCutoff = now()->subDays($aggregateDays);

            $counts['raw_deleted'] += $this->purgeRaw(
                $environment->product_id,
                $environment->environment_key,
                $rawCutoff,
            );

            $counts['hourly_deleted'] += $this->purgeHourly(
                $environment->product_id,
                $environment->environment_key,
                $aggregateCutoff,
            );

            $counts['daily_deleted'] += $this->purgeDaily(
                $environment->product_id,
                $environment->environment_key,
                $aggregateCutoff,
            );
        }

        $this->audit->log('retention.applied', $actorUserId, 'retention', null, $counts);

        return $counts;
    }

    protected function purgeRaw(string $productId, string $environment, Carbon $cutoff): int
    {
        return DB::transaction(function () use ($productId, $environment, $cutoff) {
            $deleted = 0;

            $deleted += TelemetryEvent::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $deleted += TelemetryMetric::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $deleted += TelemetryLog::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $deleted += TelemetryTraceSpan::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('started_at', '<', $cutoff)
                ->delete();

            return $deleted;
        });
    }

    protected function purgeHourly(string $productId, string $environment, Carbon $cutoff): int
    {
        return TelemetryHourlyAggregate::query()
            ->where('product_id', $productId)
            ->where('environment', $environment)
            ->where('bucket_start', '<', $cutoff)
            ->delete();
    }

    protected function purgeDaily(string $productId, string $environment, Carbon $cutoff): int
    {
        return TelemetryDailyAggregate::query()
            ->where('product_id', $productId)
            ->where('environment', $environment)
            ->where('bucket_date', '<', $cutoff->toDateString())
            ->delete();
    }
}
