<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryDailyAggregate;
use App\Models\TelemetryEvent;
use App\Models\TelemetryLog;
use App\Models\TelemetryMetric;
use App\Models\TelemetryTraceSpan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createExport(array $validated, User $actor): array
    {
        $rows = $this->queryRows($validated);
        $format = strtolower((string) $validated['format']);
        $filename = sprintf(
            'telemetry-%s-%s.%s',
            $validated['signal_family'],
            now()->format('Ymd-His'),
            $format === 'ndjson' ? 'ndjson' : $format,
        );

        $this->audit->log('export.created', $actor->id, 'export', null, [
            'format' => $format,
            'signal_family' => $validated['signal_family'],
            'row_count' => $rows->count(),
        ]);

        return [
            'stream' => true,
            'response' => $this->streamResponse($rows, $format, $filename),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function queryRows(array $validated): Collection
    {
        $start = Carbon::parse($validated['time_range']['start']);
        $end = Carbon::parse($validated['time_range']['end']);
        $productIds = $validated['product_ids'];
        $environmentKeys = $validated['environment_keys'] ?? null;
        $filters = $validated['filters'] ?? [];

        $query = match ($validated['signal_family']) {
            'events' => $this->eventQuery($productIds, $environmentKeys, $start, $end, $filters),
            'metrics' => $this->metricQuery($productIds, $environmentKeys, $start, $end, $filters),
            'logs' => $this->logQuery($productIds, $environmentKeys, $start, $end, $filters),
            'traces' => $this->traceQuery($productIds, $environmentKeys, $start, $end, $filters),
            default => TelemetryDailyAggregate::query()->whereRaw('1 = 0'),
        };

        $limit = (int) config('telemetry.query.max_limit', 1000);

        return $query->limit($limit)->get();
    }

    /**
     * @param  string[]  $productIds
     * @param  string[]|null  $environmentKeys
     * @param  array<string, mixed>  $filters
     */
    protected function eventQuery(array $productIds, ?array $environmentKeys, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = TelemetryEvent::query()
            ->whereIn('product_id', $productIds)
            ->whereBetween('occurred_at', [$start, $end]);

        if ($environmentKeys !== null) {
            $query->whereIn('environment', $environmentKeys);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }

        return $query->orderBy('occurred_at');
    }

    /**
     * @param  string[]  $productIds
     * @param  string[]|null  $environmentKeys
     * @param  array<string, mixed>  $filters
     */
    protected function metricQuery(array $productIds, ?array $environmentKeys, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = TelemetryMetric::query()
            ->whereIn('product_id', $productIds)
            ->whereBetween('occurred_at', [$start, $end]);

        if ($environmentKeys !== null) {
            $query->whereIn('environment', $environmentKeys);
        }

        if (! empty($filters['name'])) {
            $query->where('name', $filters['name']);
        }

        return $query->orderBy('occurred_at');
    }

    /**
     * @param  string[]  $productIds
     * @param  string[]|null  $environmentKeys
     * @param  array<string, mixed>  $filters
     */
    protected function logQuery(array $productIds, ?array $environmentKeys, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = TelemetryLog::query()
            ->whereIn('product_id', $productIds)
            ->whereBetween('occurred_at', [$start, $end]);

        if ($environmentKeys !== null) {
            $query->whereIn('environment', $environmentKeys);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        return $query->orderBy('occurred_at');
    }

    /**
     * @param  string[]  $productIds
     * @param  string[]|null  $environmentKeys
     * @param  array<string, mixed>  $filters
     */
    protected function traceQuery(array $productIds, ?array $environmentKeys, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = TelemetryTraceSpan::query()
            ->whereIn('product_id', $productIds)
            ->whereBetween('started_at', [$start, $end]);

        if ($environmentKeys !== null) {
            $query->whereIn('environment', $environmentKeys);
        }

        if (! empty($filters['trace_id'])) {
            $query->where('trace_id', $filters['trace_id']);
        }

        return $query->orderBy('started_at');
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    protected function streamResponse(Collection $rows, string $format, string $filename): StreamedResponse
    {
        $contentType = match ($format) {
            'csv' => 'text/csv',
            'ndjson' => 'application/x-ndjson',
            default => 'application/json',
        };

        return response()->streamDownload(function () use ($rows, $format): void {
            echo $this->formatRows($rows, $format);
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    protected function formatRows(Collection $rows, string $format): string
    {
        return match ($format) {
            'csv' => $this->toCsv($rows),
            'ndjson' => $this->toNdjson($rows),
            default => $this->toJson($rows),
        };
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    protected function toJson(Collection $rows): string
    {
        return json_encode($rows->map(fn ($row) => $this->normalizeRow($row))->values()->all(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    protected function toNdjson(Collection $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = json_encode($this->normalizeRow($row), JSON_THROW_ON_ERROR);
        }

        return implode("\n", $lines).($lines === [] ? '' : "\n");
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    protected function toCsv(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $normalized = $rows->map(fn ($row) => $this->normalizeRow($row));
        $headers = array_keys($normalized->first());
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($normalized as $row) {
            $line = [];

            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $line[] = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
            }

            fputcsv($stream, $line);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeRow(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        return $row->toArray();
    }
}
