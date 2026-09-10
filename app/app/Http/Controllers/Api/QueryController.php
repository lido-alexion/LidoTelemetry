<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Domain\Telemetry\AnalyticsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryController extends Controller
{
    public function __construct(
        protected TelemetryQueryServiceInterface $queryService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $definition = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
            'signal_family' => ['required', 'string', 'in:events,metrics,logs,traces'],
            'time_range' => ['required', 'array'],
            'time_range.start' => ['required', 'date'],
            'time_range.end' => ['required', 'date', 'after:time_range.start'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable', 'array'],
            'aggregations' => ['nullable', 'array'],
            'order_by' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('telemetry.query.max_limit')],
            'offset' => ['nullable', 'integer', 'min:0'],
            'analysis_type' => ['nullable', 'string', 'in:search,aggregate,group_by,time_series,sessions,funnels'],
            'steps' => ['nullable', 'array'],
            'steps.*' => ['array'],
        ]);

        $this->assertProductAccess($request, $definition['product_ids'], $definition['environment_keys'] ?? null);

        $query = AnalyticsQuery::fromArray($definition);
        $analysisType = $definition['analysis_type'] ?? 'aggregate';

        $result = match ($analysisType) {
            'search' => $this->queryService->search($query),
            'group_by' => $this->queryService->groupBy($query),
            'time_series' => $this->queryService->timeSeries($query),
            'sessions' => $this->queryService->sessions($query),
            'funnels' => $this->queryService->funnels($definition['steps'] ?? [], $query),
            default => $this->queryService->aggregate($query),
        };

        return response()->json([
            'analysis_type' => $analysisType,
            'data' => $result,
        ]);
    }

    /**
     * @param  string[]  $productIds
     * @param  string[]|null  $environmentKeys
     */
    protected function assertProductAccess(Request $request, array $productIds, ?array $environmentKeys): void
    {
        $allowedProducts = $request->attributes->get('telemetry_product_ids');
        $allowedEnvironments = $request->attributes->get('telemetry_environment_keys');

        if ($allowedProducts !== null) {
            foreach ($productIds as $productId) {
                if (! in_array($productId, $allowedProducts, true)) {
                    abort(403, 'Token does not grant access to one or more requested products.');
                }
            }
        }

        if ($allowedEnvironments !== null && $environmentKeys !== null) {
            foreach ($environmentKeys as $environmentKey) {
                if (! in_array($environmentKey, $allowedEnvironments, true)) {
                    abort(403, 'Token does not grant access to one or more requested environments.');
                }
            }
        }
    }
}
