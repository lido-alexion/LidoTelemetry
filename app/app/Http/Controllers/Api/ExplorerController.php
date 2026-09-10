<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Domain\Telemetry\AnalyticsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExplorerController extends Controller
{
    public function __construct(
        protected TelemetryQueryServiceInterface $queryService,
    ) {}

    public function events(Request $request): JsonResponse
    {
        return $this->explore($request, 'events');
    }

    public function metrics(Request $request): JsonResponse
    {
        return $this->explore($request, 'metrics');
    }

    public function logs(Request $request): JsonResponse
    {
        return $this->explore($request, 'logs');
    }

    public function traces(Request $request): JsonResponse
    {
        return $this->explore($request, 'traces');
    }

    public function sessions(Request $request): JsonResponse
    {
        $query = $this->buildQueryFromRequest($request, 'events');
        $this->assertProductAccess($request, $query->productIds, $query->environmentKeys);

        return response()->json([
            'data' => $this->queryService->sessions($query),
        ]);
    }

    public function views(Request $request): JsonResponse
    {
        $query = $this->buildQueryFromRequest($request, 'events');
        $this->assertProductAccess($request, $query->productIds, $query->environmentKeys);

        return response()->json([
            'data' => $this->queryService->views($query),
        ]);
    }

    protected function explore(Request $request, string $signalFamily): JsonResponse
    {
        $query = $this->buildQueryFromRequest($request, $signalFamily);
        $this->assertProductAccess($request, $query->productIds, $query->environmentKeys);

        return response()->json([
            'data' => $this->queryService->search($query),
        ]);
    }

    protected function buildQueryFromRequest(Request $request, string $signalFamily): AnalyticsQuery
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid'],
            'environment' => ['required', 'string', 'max:64'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'filters' => ['nullable'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('telemetry.query.max_limit')],
            'offset' => ['nullable', 'integer', 'min:0'],
            'order_by' => ['nullable', 'array'],
        ]);

        $filters = $this->normalizeFilters($validated['filters'] ?? [], $validated['search'] ?? null);

        return AnalyticsQuery::fromArray([
            'product_ids' => [$validated['product_id']],
            'environment_keys' => [$validated['environment']],
            'signal_family' => $signalFamily,
            'time_range' => [
                'start' => $validated['start'],
                'end' => $validated['end'],
            ],
            'filters' => $filters,
            'order_by' => $validated['order_by'] ?? [],
            'limit' => $validated['limit'] ?? config('telemetry.query.default_limit'),
            'offset' => $validated['offset'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $filters
     * @return list<array<string, mixed>>
     */
    protected function normalizeFilters(mixed $filters, ?string $search = null): array
    {
        if (is_string($filters)) {
            return [['field' => 'search', 'operator' => 'contains', 'value' => $filters]];
        }

        if (is_array($filters) && isset($filters['search']) && is_string($filters['search'])) {
            $search = $filters['search'];
            unset($filters['search']);
        }

        $normalized = [];

        if (is_array($filters)) {
            if (array_is_list($filters)) {
                $normalized = $filters;
            } else {
                foreach ($filters as $field => $value) {
                    if ($field === 'search') {
                        continue;
                    }

                    $normalized[] = is_array($value)
                        ? $value
                        : ['field' => (string) $field, 'operator' => 'eq', 'value' => $value];
                }
            }
        }

        if ($search !== null && $search !== '') {
            $normalized[] = ['field' => 'search', 'operator' => 'contains', 'value' => $search];
        }

        return $normalized;
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
