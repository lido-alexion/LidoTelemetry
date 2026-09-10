<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Domain\Telemetry\AnalyticsQuery;
use App\Http\Controllers\Controller;
use App\Models\TelemetrySavedAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedAnalysisController extends Controller
{
    public function __construct(
        protected TelemetryQueryServiceInterface $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $analyses = TelemetrySavedAnalysis::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $analyses]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'analysis_type' => ['required', 'string', 'max:64'],
            'definition' => ['required', 'array'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
        ]);

        $analysis = TelemetrySavedAnalysis::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $analysis], 201);
    }

    public function show(Request $request, TelemetrySavedAnalysis $savedAnalysis): JsonResponse
    {
        $this->authorizeOwnership($request, $savedAnalysis);

        return response()->json(['data' => $savedAnalysis]);
    }

    public function update(Request $request, TelemetrySavedAnalysis $savedAnalysis): JsonResponse
    {
        $this->authorizeOwnership($request, $savedAnalysis);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'analysis_type' => ['sometimes', 'string', 'max:64'],
            'definition' => ['sometimes', 'array'],
            'product_ids' => ['sometimes', 'array', 'min:1'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
        ]);

        $savedAnalysis->fill($validated);
        $savedAnalysis->save();

        return response()->json(['data' => $savedAnalysis->fresh()]);
    }

    public function destroy(Request $request, TelemetrySavedAnalysis $savedAnalysis): JsonResponse
    {
        $this->authorizeOwnership($request, $savedAnalysis);

        $savedAnalysis->delete();

        return response()->json(['message' => 'Saved analysis deleted.']);
    }

    public function run(Request $request, TelemetrySavedAnalysis $savedAnalysis): JsonResponse
    {
        $this->authorizeOwnership($request, $savedAnalysis);

        $definition = array_merge($savedAnalysis->definition ?? [], [
            'product_ids' => $savedAnalysis->product_ids ?? [],
            'environment_keys' => $savedAnalysis->environment_keys ?? [],
        ]);

        $query = AnalyticsQuery::fromArray($definition);
        $analysisType = (string) ($savedAnalysis->analysis_type ?? 'search');

        $data = match ($analysisType) {
            'aggregate' => $this->queryService->aggregate($query),
            'group_by' => $this->queryService->groupBy($query),
            'time_series' => $this->queryService->timeSeries($query),
            'sessions' => $this->queryService->sessions($query),
            'views' => $this->queryService->views($query),
            'funnels' => $this->queryService->funnels($definition['steps'] ?? [], $query),
            'journeys' => $this->queryService->journeys($query),
            'retention' => $this->queryService->retention($query),
            default => $this->queryService->search($query),
        };

        return response()->json(['data' => $data]);
    }

    protected function authorizeOwnership(Request $request, TelemetrySavedAnalysis $analysis): void
    {
        if ($analysis->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this saved analysis.');
        }
    }
}
