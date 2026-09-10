<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryDailyAggregate;
use App\Models\TelemetryEvent;
use App\Models\TelemetryHourlyAggregate;
use App\Models\TelemetryLog;
use App\Models\TelemetryMetadataKey;
use App\Models\TelemetryMetric;
use App\Models\TelemetryProductEnvironment;
use App\Models\TelemetrySession;
use App\Models\TelemetryTraceSpan;
use App\Models\TelemetryView;
use App\Models\TelemetryViewDurationSummary;
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
            'sessions_deleted' => 0,
            'views_deleted' => 0,
            'view_summaries_deleted' => 0,
            'metadata_keys_deleted' => 0,
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

            $rawPurgeCounts = $this->purgeRaw(
                $environment->product_id,
                $environment->environment_key,
                $rawCutoff,
            );

            $counts['raw_deleted'] += $rawPurgeCounts['raw'];
            $counts['sessions_deleted'] += $rawPurgeCounts['sessions'];
            $counts['views_deleted'] += $rawPurgeCounts['views'];
            $counts['view_summaries_deleted'] += $rawPurgeCounts['view_summaries'];
            $counts['metadata_keys_deleted'] += $rawPurgeCounts['metadata_keys'];

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

    /**
     * @return array<string, int>
     */
    protected function purgeRaw(string $productId, string $environment, Carbon $cutoff): array
    {
        return DB::transaction(function () use ($productId, $environment, $cutoff) {
            $counts = [
                'raw' => 0,
                'sessions' => 0,
                'views' => 0,
                'view_summaries' => 0,
                'metadata_keys' => 0,
            ];

            $counts['raw'] += TelemetryEvent::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $counts['raw'] += TelemetryMetric::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $counts['raw'] += TelemetryLog::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            $counts['raw'] += TelemetryTraceSpan::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('started_at', '<', $cutoff)
                ->delete();

            $counts['view_summaries'] += TelemetryViewDurationSummary::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('summary_date', '<', $cutoff->toDateString())
                ->delete();

            $counts['views'] += TelemetryView::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('started_at', '<', $cutoff)
                ->delete();

            $counts['sessions'] += TelemetrySession::query()
                ->where('product_id', $productId)
                ->where('environment', $environment)
                ->where('started_at', '<', $cutoff)
                ->delete();

            $counts['metadata_keys'] += TelemetryMetadataKey::query()
                ->where('product_id', $productId)
                ->where('last_seen_at', '<', $cutoff)
                ->delete();

            return $counts;
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
