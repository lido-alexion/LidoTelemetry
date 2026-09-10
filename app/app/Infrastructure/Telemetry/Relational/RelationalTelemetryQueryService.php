<?php

namespace App\Infrastructure\Telemetry\Relational;

use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Domain\Telemetry\AnalyticsQuery;
use App\Models\TelemetryEvent;
use App\Models\TelemetryLog;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use App\Models\TelemetryTraceSpan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RelationalTelemetryQueryService implements TelemetryQueryServiceInterface
{
    public function search(AnalyticsQuery $query): array
    {
        $builder = $this->baseBuilder($query);
        $limit = $query->cappedLimit();
        $offset = max($query->offset, 0);

        $total = (clone $builder)->count();

        $rows = $builder
            ->orderByDesc($this->timestampColumn($query))
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => $row->toArray())
            ->all();

        return [
            'data' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function aggregate(AnalyticsQuery $query): array
    {
        $builder = $this->baseBuilder($query);
        $aggregations = $query->aggregations !== [] ? $query->aggregations : [['function' => 'count']];
        $results = [];

        foreach ($aggregations as $aggregation) {
            $alias = (string) ($aggregation['alias'] ?? $aggregation['function'] ?? 'count');
            $function = strtolower((string) ($aggregation['function'] ?? 'count'));
            $field = (string) ($aggregation['field'] ?? '*');

            $expression = match ($function) {
                'sum' => "SUM({$this->qualifiedField($query, $field)})",
                'avg' => "AVG({$this->qualifiedField($query, $field)})",
                'min' => "MIN({$this->qualifiedField($query, $field)})",
                'max' => "MAX({$this->qualifiedField($query, $field)})",
                'count_distinct', 'unique' => 'COUNT(DISTINCT '.$this->qualifiedField($query, $field).')',
                default => 'COUNT(*)',
            };

            $results[$alias] = (clone $builder)->selectRaw("{$expression} as value")->value('value');
        }

        return $results;
    }

    public function groupBy(AnalyticsQuery $query): array
    {
        $builder = $this->baseBuilder($query);
        $groupFields = $query->groupBy !== [] ? $query->groupBy : ['event_type'];
        $selects = [];
        $groupExpressions = [];

        foreach ($groupFields as $index => $field) {
            $alias = 'dimension_'.$index;
            $expression = $this->dimensionExpression($query, $field);
            $selects[] = DB::raw("{$expression} as `{$alias}`");
            $groupExpressions[] = DB::raw($expression);
        }

        $aggregations = $query->aggregations !== [] ? $query->aggregations : [['function' => 'count', 'alias' => 'count']];

        foreach ($aggregations as $aggregation) {
            $alias = (string) ($aggregation['alias'] ?? $aggregation['function'] ?? 'count');
            $function = strtolower((string) ($aggregation['function'] ?? 'count'));
            $field = (string) ($aggregation['field'] ?? '*');

            $expression = match ($function) {
                'sum' => "SUM({$this->qualifiedField($query, $field)})",
                'avg' => "AVG({$this->qualifiedField($query, $field)})",
                'min' => "MIN({$this->qualifiedField($query, $field)})",
                'max' => "MAX({$this->qualifiedField($query, $field)})",
                'count_distinct', 'unique' => 'COUNT(DISTINCT '.$this->qualifiedField($query, $field).')',
                default => 'COUNT(*)',
            };

            $selects[] = DB::raw("{$expression} as `{$alias}`");
        }

        $rows = $builder
            ->select($selects)
            ->groupBy(...$groupExpressions)
            ->orderByDesc($aggregations[0]['alias'] ?? 'count')
            ->limit($query->cappedLimit())
            ->get();

        return $rows->map(function ($row) use ($groupFields): array {
            $payload = [];

            foreach ($groupFields as $index => $field) {
                $payload[$field] = $row->{'dimension_'.$index};
            }

            foreach ((array) $row->getAttributes() as $key => $value) {
                if (! str_starts_with($key, 'dimension_')) {
                    $payload[$key] = $value;
                }
            }

            return $payload;
        })->all();
    }

    public function timeSeries(AnalyticsQuery $query): array
    {
        $builder = $this->baseBuilder($query);
        $bucket = $query->timeBucket ?? 'hour';
        $timestampColumn = $this->timestampColumn($query);
        $bucketExpression = match ($bucket) {
            'day' => "DATE({$timestampColumn})",
            'week' => "DATE_FORMAT({$timestampColumn}, '%x-%v')",
            'month' => "DATE_FORMAT({$timestampColumn}, '%Y-%m')",
            default => "DATE_FORMAT({$timestampColumn}, '%Y-%m-%d %H:00:00')",
        };

        $aggregations = $query->aggregations !== [] ? $query->aggregations : [['function' => 'count', 'alias' => 'count']];
        $selects = [DB::raw("{$bucketExpression} as bucket")];

        foreach ($aggregations as $aggregation) {
            $alias = (string) ($aggregation['alias'] ?? $aggregation['function'] ?? 'count');
            $function = strtolower((string) ($aggregation['function'] ?? 'count'));
            $field = (string) ($aggregation['field'] ?? '*');

            $expression = match ($function) {
                'sum' => "SUM({$this->qualifiedField($query, $field)})",
                'avg' => "AVG({$this->qualifiedField($query, $field)})",
                'min' => "MIN({$this->qualifiedField($query, $field)})",
                'max' => "MAX({$this->qualifiedField($query, $field)})",
                'count_distinct', 'unique' => 'COUNT(DISTINCT '.$this->qualifiedField($query, $field).')',
                default => 'COUNT(*)',
            };

            $selects[] = DB::raw("{$expression} as `{$alias}`");
        }

        return $builder
            ->select($selects)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row): array => (array) $row->getAttributes())
            ->all();
    }

    public function sessions(AnalyticsQuery $query): array
    {
        $builder = TelemetrySession::query();
        $this->applyProductScope($builder, $query, 'telemetry_sessions');
        $this->applyEnvironmentScope($builder, $query, 'telemetry_sessions');
        $this->applyTimeRange($builder, $query, 'telemetry_sessions', 'started_at');
        $this->applyFilters($builder, $query, 'telemetry_sessions');
        $this->applyMetadataPredicates($builder, $query, 'telemetry_sessions', 'metadata_snapshot');

        $limit = $query->cappedLimit();
        $offset = max($query->offset, 0);
        $total = (clone $builder)->count();

        $rows = $builder
            ->orderByDesc('started_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => $row->toArray())
            ->all();

        return [
            'data' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function funnels(array $steps, AnalyticsQuery $query): array
    {
        if ($steps === []) {
            return [];
        }

        $results = [];
        $previousSessionIds = null;

        foreach ($steps as $index => $step) {
            $eventType = (string) ($step['event_type'] ?? $step['eventType'] ?? '');
            $stepQuery = AnalyticsQuery::fromArray(array_merge($query->toArray(), [
                'filters' => array_merge($query->filters, [
                    ['field' => 'event_type', 'operator' => 'eq', 'value' => $eventType],
                ]),
                'signal_family' => AnalyticsQuery::SIGNAL_EVENTS,
            ]));

            $builder = $this->baseBuilder($stepQuery)->select('session_id');
            $builder->whereNotNull('session_id');

            if ($previousSessionIds !== null) {
                $builder->whereIn('session_id', $previousSessionIds);
            }

            $sessionIds = $builder
                ->distinct()
                ->pluck('session_id')
                ->filter()
                ->values()
                ->all();

            $results[] = [
                'step' => $index + 1,
                'event_type' => $eventType,
                'label' => $step['label'] ?? $eventType,
                'session_count' => count($sessionIds),
            ];

            $previousSessionIds = $sessionIds;

            if ($previousSessionIds === []) {
                break;
            }
        }

        $firstCount = $results[0]['session_count'] ?? 0;

        return array_map(function (array $row) use ($firstCount): array {
            $row['conversion_rate'] = $firstCount > 0
                ? round($row['session_count'] / $firstCount, 4)
                : 0.0;

            return $row;
        }, $results);
    }

    private function baseBuilder(AnalyticsQuery $query): Builder
    {
        $builder = match ($query->signalFamily) {
            AnalyticsQuery::SIGNAL_METRICS => TelemetryMetric::query(),
            AnalyticsQuery::SIGNAL_LOGS => TelemetryLog::query(),
            AnalyticsQuery::SIGNAL_TRACES => TelemetryTraceSpan::query(),
            default => TelemetryEvent::query(),
        };

        $table = $builder->getModel()->getTable();
        $this->applyProductScope($builder, $query, $table);
        $this->applyEnvironmentScope($builder, $query, $table);
        $this->applyTimeRange($builder, $query, $table, $this->timestampColumn($query));
        $this->applyFilters($builder, $query, $table);
        $this->applyMetadataPredicates(
            $builder,
            $query,
            $table,
            $this->jsonColumn($query),
        );

        return $builder;
    }

    private function applyProductScope(Builder $builder, AnalyticsQuery $query, string $table): void
    {
        if ($query->productIds !== []) {
            $builder->whereIn("{$table}.product_id", $query->productIds);
        }
    }

    private function applyEnvironmentScope(Builder $builder, AnalyticsQuery $query, string $table): void
    {
        if ($query->environmentKeys !== []) {
            $builder->whereIn("{$table}.environment", $query->environmentKeys);
        }
    }

    private function applyTimeRange(Builder $builder, AnalyticsQuery $query, string $table, string $column): void
    {
        if ($query->timeRangeStart !== null) {
            $builder->where("{$table}.{$column}", '>=', $query->timeRangeStart);
        }

        if ($query->timeRangeEnd !== null) {
            $builder->where("{$table}.{$column}", '<=', $query->timeRangeEnd);
        }
    }

    private function applyFilters(Builder $builder, AnalyticsQuery $query, string $table): void
    {
        foreach ($query->filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
            $value = $filter['value'] ?? null;

            if ($field === '') {
                continue;
            }

            if (str_starts_with($field, 'metadata.')) {
                $path = substr($field, strlen('metadata.'));
                $expression = $this->jsonExtractExpression("{$table}.{$this->jsonColumnFromTable($table)}", $path);

                $this->applyOperator($builder, $expression, $operator, $value);

                continue;
            }

            $column = "{$table}.{$field}";
            $this->applyOperator($builder, $column, $operator, $value);
        }
    }

    private function applyMetadataPredicates(Builder $builder, AnalyticsQuery $query, string $table, ?string $jsonColumn): void
    {
        if ($jsonColumn === null) {
            return;
        }

        foreach ($query->metadataPathPredicates as $predicate) {
            $path = (string) ($predicate['path'] ?? $predicate['key'] ?? '');
            $operator = strtolower((string) ($predicate['operator'] ?? 'eq'));
            $value = $predicate['value'] ?? null;

            if ($path === '') {
                continue;
            }

            $expression = $this->jsonExtractExpression("{$table}.{$jsonColumn}", $path);
            $this->applyOperator($builder, $expression, $operator, $value);
        }
    }

    private function applyOperator(Builder $builder, string $expression, string $operator, mixed $value): void
    {
        match ($operator) {
            'neq', 'ne', '!=' => $builder->whereRaw("{$expression} != ?", [$value]),
            'gt', '>' => $builder->whereRaw("{$expression} > ?", [$value]),
            'gte', '>=' => $builder->whereRaw("{$expression} >= ?", [$value]),
            'lt', '<' => $builder->whereRaw("{$expression} < ?", [$value]),
            'lte', '<=' => $builder->whereRaw("{$expression} <= ?", [$value]),
            'in' => $builder->whereIn(DB::raw($expression), (array) $value),
            'not_in' => $builder->whereNotIn(DB::raw($expression), (array) $value),
            'contains', 'like' => $builder->whereRaw("{$expression} LIKE ?", ['%'.$value.'%']),
            'exists' => $builder->whereRaw("JSON_EXTRACT({$expression}, '$') IS NOT NULL"),
            'not_exists' => $builder->whereRaw("JSON_EXTRACT({$expression}, '$') IS NULL"),
            default => $builder->whereRaw("{$expression} = ?", [$value]),
        };
    }

    private function dimensionExpression(AnalyticsQuery $query, string $field): string
    {
        if (str_starts_with($field, 'metadata.')) {
            $path = substr($field, strlen('metadata.'));
            $table = $this->tableForSignal($query);

            return $this->jsonExtractExpression("{$table}.{$this->jsonColumn($query)}", $path);
        }

        return $this->qualifiedField($query, $field);
    }

    private function qualifiedField(AnalyticsQuery $query, string $field): string
    {
        if ($field === '*' || str_contains($field, '(')) {
            return $field;
        }

        if (str_starts_with($field, 'metadata.')) {
            $path = substr($field, strlen('metadata.'));
            $table = $this->tableForSignal($query);

            return $this->jsonExtractExpression("{$table}.{$this->jsonColumn($query)}", $path);
        }

        return $this->tableForSignal($query).'.'.$field;
    }

    private function tableForSignal(AnalyticsQuery $query): string
    {
        return match ($query->signalFamily) {
            AnalyticsQuery::SIGNAL_METRICS => 'telemetry_metrics',
            AnalyticsQuery::SIGNAL_LOGS => 'telemetry_logs',
            AnalyticsQuery::SIGNAL_TRACES => 'telemetry_trace_spans',
            default => 'telemetry_events',
        };
    }

    private function timestampColumn(AnalyticsQuery $query): string
    {
        return match ($query->signalFamily) {
            AnalyticsQuery::SIGNAL_TRACES => 'started_at',
            default => 'occurred_at',
        };
    }

    private function jsonColumn(AnalyticsQuery $query): ?string
    {
        return match ($query->signalFamily) {
            AnalyticsQuery::SIGNAL_METRICS => 'dimensions',
            AnalyticsQuery::SIGNAL_LOGS => 'metadata',
            AnalyticsQuery::SIGNAL_TRACES => 'attributes',
            default => 'metadata',
        };
    }

    private function jsonColumnFromTable(string $table): string
    {
        return match ($table) {
            'telemetry_metrics' => 'dimensions',
            'telemetry_logs' => 'metadata',
            'telemetry_trace_spans' => 'attributes',
            'telemetry_sessions' => 'metadata_snapshot',
            default => 'metadata',
        };
    }

    private function jsonExtractExpression(string $column, string $path): string
    {
        $jsonPath = '$'.(str_starts_with($path, '.') ? $path : '.'.$path);

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$jsonPath}'))";
    }
}
