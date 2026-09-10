<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exports,
    ) {}

    public function store(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'format' => ['required', 'string', 'in:csv,json,ndjson'],
            'export_type' => ['required', 'string', 'in:raw,aggregates,sessions,views,saved_analysis'],
            'signal_family' => ['nullable', 'string', 'in:events,metrics,logs,traces'],
            'product_ids' => ['required_unless:export_type,saved_analysis', 'array', 'min:1'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
            'time_range' => ['required', 'array'],
            'time_range.start' => ['required', 'date'],
            'time_range.end' => ['required', 'date', 'after:time_range.start'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable', 'array'],
            'aggregations' => ['nullable', 'array'],
            'analysis_type' => ['nullable', 'string', 'in:aggregate,group_by,time_series'],
            'saved_analysis_id' => ['required_if:export_type,saved_analysis', 'uuid'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        if (($validated['export_type'] ?? 'raw') === 'raw' && empty($validated['signal_family'])) {
            $validated['signal_family'] = 'events';
        }

        if (! empty($validated['product_ids'])) {
            $this->assertProductAccess($request, $validated['product_ids'], $validated['environment_keys'] ?? null);
        }

        $export = $this->exports->createExport($validated, $request->user());

        if ($export['stream'] ?? false) {
            return $export['response'];
        }

        return response()->json($export, 202);
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
