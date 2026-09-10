<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryDailyAggregate;
use App\Models\TelemetryDeletionTombstone;
use App\Models\TelemetryEvent;
use App\Models\TelemetryHourlyAggregate;
use App\Models\TelemetryLog;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use App\Models\TelemetryTraceSpan;
use App\Models\TelemetryView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeletionService
{
    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_ENVIRONMENT = 'environment';

    public const SCOPE_USER = 'user';

    public const SCOPE_ANONYMOUS = 'anonymous';

    public const SCOPE_SESSION = 'session';

    public const SCOPE_RANGE = 'range';

    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{tombstone_id: string, deleted_counts: array<string, int>}
     */
    public function deleteByScope(string $scopeType, array $params, ?int $requestedByUserId = null): array
    {
        $this->validateScope($scopeType, $params);

        $counts = match ($scopeType) {
            self::SCOPE_PRODUCT => $this->deleteProductScope($params),
            self::SCOPE_ENVIRONMENT => $this->deleteEnvironmentScope($params),
            self::SCOPE_USER => $this->deleteUserScope($params),
            self::SCOPE_ANONYMOUS => $this->deleteAnonymousScope($params),
            self::SCOPE_SESSION => $this->deleteSessionScope($params),
            self::SCOPE_RANGE => $this->deleteRangeScope($params),
            default => throw ValidationException::withMessages([
                'scope_type' => ['Unsupported deletion scope.'],
            ]),
        };

        $tombstone = TelemetryDeletionTombstone::query()->create([
            'id' => (string) Str::uuid(),
            'product_id' => $params['product_id'] ?? null,
            'environment' => $params['environment'] ?? null,
            'scope_type' => $scopeType,
            'scope_value' => $params['scope_value'] ?? null,
            'range_start' => isset($params['range_start']) ? Carbon::parse($params['range_start']) : null,
            'range_end' => isset($params['range_end']) ? Carbon::parse($params['range_end']) : null,
            'requested_by_user_id' => $requestedByUserId,
            'deleted_at' => now(),
            'meta' => ['deleted_counts' => $counts],
        ]);

        $this->audit->log('deletion.executed', $requestedByUserId, 'deletion_tombstone', $tombstone->id, [
            'scope_type' => $scopeType,
            'deleted_counts' => $counts,
        ]);

        return [
            'tombstone_id' => $tombstone->id,
            'deleted_counts' => $counts,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteProductScope(array $params): array
    {
        $productId = $params['product_id'];

        return $this->deleteAcrossTables(fn ($query) => $query->where('product_id', $productId));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteEnvironmentScope(array $params): array
    {
        return $this->deleteAcrossTables(function ($query) use ($params) {
            $query->where('product_id', $params['product_id'])
                ->where('environment', $params['environment']);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteUserScope(array $params): array
    {
        return $this->deleteAcrossTables(function ($query) use ($params) {
            $query->where('product_id', $params['product_id'])
                ->where('user_id', $params['scope_value']);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteAnonymousScope(array $params): array
    {
        return $this->deleteAcrossTables(function ($query) use ($params) {
            $query->where('product_id', $params['product_id'])
                ->where('anonymous_id', $params['scope_value']);
        }, includeSessions: true);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteSessionScope(array $params): array
    {
        $sessionId = $params['scope_value'];

        $counts = $this->deleteAcrossTables(
            fn ($query) => $query->where('session_id', $sessionId),
            includeSessions: true,
        );

        $counts['views'] += TelemetryView::query()->where('session_id', $sessionId)->delete();

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    protected function deleteRangeScope(array $params): array
    {
        $from = Carbon::parse($params['range_start']);
        $to = Carbon::parse($params['range_end']);

        return $this->deleteAcrossTables(function ($query) use ($params, $from, $to) {
            $query->where('product_id', $params['product_id']);

            if (isset($params['environment'])) {
                $query->where('environment', $params['environment']);
            }

            $query->whereBetween('occurred_at', [$from, $to]);
        });
    }

    /**
     * @return array<string, int>
     */
    protected function deleteAcrossTables(callable $constraint, bool $includeSessions = false): array
    {
        return DB::transaction(function () use ($constraint, $includeSessions) {
            $counts = [
                'events' => 0,
                'metrics' => 0,
                'logs' => 0,
                'traces' => 0,
                'sessions' => 0,
                'views' => 0,
                'hourly_aggregates' => 0,
                'daily_aggregates' => 0,
            ];

            $counts['events'] = $this->applyConstraint(TelemetryEvent::query(), $constraint)->delete();
            $counts['metrics'] = $this->applyConstraint(TelemetryMetric::query(), $constraint)->delete();
            $counts['logs'] = $this->applyConstraint(TelemetryLog::query(), $constraint)->delete();
            $counts['traces'] = $this->applyConstraint(TelemetryTraceSpan::query(), $constraint)->delete();

            if ($includeSessions) {
                $counts['sessions'] = $this->applyConstraint(TelemetrySession::query(), $constraint)->delete();
            }

            $counts['hourly_aggregates'] = $this->applyConstraint(TelemetryHourlyAggregate::query(), function ($query) use ($constraint) {
                $constraint($query);
            })->delete();

            $counts['daily_aggregates'] = $this->applyConstraint(TelemetryDailyAggregate::query(), function ($query) use ($constraint) {
                $constraint($query);
            })->delete();

            return $counts;
        });
    }

    protected function applyConstraint($query, callable $constraint)
    {
        $constraint($query);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function validateScope(string $scopeType, array $params): void
    {
        if (! in_array($scopeType, [
            self::SCOPE_PRODUCT,
            self::SCOPE_ENVIRONMENT,
            self::SCOPE_USER,
            self::SCOPE_ANONYMOUS,
            self::SCOPE_SESSION,
            self::SCOPE_RANGE,
        ], true)) {
            throw ValidationException::withMessages([
                'scope_type' => ['Unsupported deletion scope.'],
            ]);
        }

        if (in_array($scopeType, [self::SCOPE_PRODUCT, self::SCOPE_ENVIRONMENT, self::SCOPE_USER, self::SCOPE_ANONYMOUS, self::SCOPE_RANGE], true)
            && empty($params['product_id'])) {
            throw ValidationException::withMessages([
                'product_id' => ['Product ID is required for this deletion scope.'],
            ]);
        }

        if ($scopeType === self::SCOPE_ENVIRONMENT && empty($params['environment'])) {
            throw ValidationException::withMessages([
                'environment' => ['Environment is required for environment scope deletion.'],
            ]);
        }

        if (in_array($scopeType, [self::SCOPE_USER, self::SCOPE_ANONYMOUS, self::SCOPE_SESSION], true)
            && empty($params['scope_value'])) {
            throw ValidationException::withMessages([
                'scope_value' => ['Scope value is required.'],
            ]);
        }

        if ($scopeType === self::SCOPE_RANGE && (empty($params['range_start']) || empty($params['range_end']))) {
            throw ValidationException::withMessages([
                'range' => ['Range start and end are required.'],
            ]);
        }
    }

    public function isTombstoned(string $productId, ?string $environment, ?string $userId, ?string $sessionId, Carbon $occurredAt): bool
    {
        return TelemetryDeletionTombstone::query()
            ->where(function ($query) use ($productId, $environment, $userId, $sessionId, $occurredAt) {
                $query->where('scope_type', self::SCOPE_PRODUCT)
                    ->where('product_id', $productId);

                $query->orWhere(function ($q) use ($productId, $environment) {
                    $q->where('scope_type', self::SCOPE_ENVIRONMENT)
                        ->where('product_id', $productId)
                        ->where('environment', $environment);
                });

                if ($userId) {
                    $query->orWhere(function ($q) use ($productId, $userId) {
                        $q->where('scope_type', self::SCOPE_USER)
                            ->where('product_id', $productId)
                            ->where('scope_value', $userId);
                    });
                }

                if ($sessionId) {
                    $query->orWhere(function ($q) use ($sessionId) {
                        $q->where('scope_type', self::SCOPE_SESSION)
                            ->where('scope_value', $sessionId);
                    });
                }

                $query->orWhere(function ($q) use ($productId, $environment, $occurredAt) {
                    $q->where('scope_type', self::SCOPE_RANGE)
                        ->where('product_id', $productId)
                        ->when($environment, fn ($inner) => $inner->where('environment', $environment))
                        ->where('range_start', '<=', $occurredAt)
                        ->where('range_end', '>=', $occurredAt);
                });
            })
            ->exists();
    }
}
