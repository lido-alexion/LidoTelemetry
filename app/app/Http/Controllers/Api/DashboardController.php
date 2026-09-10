<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Domain\Telemetry\AnalyticsQuery;
use App\Http\Controllers\Controller;
use App\Models\TelemetryDashboard;
use App\Models\TelemetryDashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        protected TelemetryQueryServiceInterface $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dashboards = TelemetryDashboard::query()
            ->where(function ($query) use ($request): void {
                $query->where('is_builtin', true)
                    ->orWhere('user_id', $request->user()?->id);
            })
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $dashboards]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('telemetry_dashboards', 'slug')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
            'layout' => ['nullable', 'array'],
            'widgets' => ['nullable', 'array'],
            'widgets.*.widget_type' => ['required_with:widgets', 'string', 'max:64'],
            'widgets.*.title' => ['required_with:widgets', 'string', 'max:255'],
            'widgets.*.config' => ['required_with:widgets', 'array'],
            'widgets.*.position' => ['nullable', 'integer', 'min:0'],
            'widgets.*.width' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.height' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $dashboard = TelemetryDashboard::query()->create([
            'user_id' => $request->user()?->id,
            'slug' => $validated['slug'] ?? Str::slug($validated['name']).'-'.Str::random(6),
            'name' => $validated['name'],
            'dashboard_type' => 'custom',
            'product_ids' => $validated['product_ids'] ?? null,
            'environment_keys' => $validated['environment_keys'] ?? null,
            'layout' => $validated['layout'] ?? null,
            'is_builtin' => false,
        ]);

        $this->syncWidgets($dashboard, $validated['widgets'] ?? []);

        return response()->json(['data' => $dashboard->load('widgets')], 201);
    }

    public function show(string $dashboard): JsonResponse
    {
        $model = $this->findAccessibleDashboard($dashboard);

        return response()->json(['data' => $model->load('widgets')]);
    }

    public function update(Request $request, string $dashboard): JsonResponse
    {
        $model = $this->findAccessibleDashboard($dashboard, customOnly: true);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
            'layout' => ['nullable', 'array'],
            'widgets' => ['nullable', 'array'],
            'widgets.*.widget_type' => ['required_with:widgets', 'string', 'max:64'],
            'widgets.*.title' => ['required_with:widgets', 'string', 'max:255'],
            'widgets.*.config' => ['required_with:widgets', 'array'],
            'widgets.*.position' => ['nullable', 'integer', 'min:0'],
            'widgets.*.width' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.height' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $model->fill(collect($validated)->except('widgets')->all());
        $model->save();

        if (array_key_exists('widgets', $validated)) {
            $this->syncWidgets($model, $validated['widgets'] ?? []);
        }

        return response()->json(['data' => $model->fresh()->load('widgets')]);
    }

    public function destroy(string $dashboard): JsonResponse
    {
        $model = $this->findAccessibleDashboard($dashboard, customOnly: true);
        $model->delete();

        return response()->json(['message' => 'Dashboard deleted.']);
    }

    public function builtinData(Request $request, string $slug): JsonResponse
    {
        $dashboard = TelemetryDashboard::query()
            ->where('is_builtin', true)
            ->where('slug', $slug)
            ->with('widgets')
            ->firstOrFail();

        $validated = $request->validate([
            'product_id' => ['required', 'uuid'],
            'environment' => ['required', 'string', 'max:64'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $widgetData = [];

        foreach ($dashboard->widgets as $widget) {
            $config = $widget->config;
            $queryDefinition = $config['query'] ?? $config;

            $queryDefinition['product_ids'] = [$validated['product_id']];
            $queryDefinition['environment_keys'] = [$validated['environment']];
            $queryDefinition['time_range'] = [
                'start' => $validated['start'],
                'end' => $validated['end'],
            ];

            $query = AnalyticsQuery::fromArray($queryDefinition);
            $analysisType = $config['analysis_type'] ?? 'aggregate';

            $widgetData[] = [
                'widget_id' => $widget->id,
                'title' => $widget->title,
                'widget_type' => $widget->widget_type,
                'analysis_type' => $analysisType,
                'data' => match ($analysisType) {
                    'search' => $this->queryService->search($query),
                    'group_by' => $this->queryService->groupBy($query),
                    'time_series' => $this->queryService->timeSeries($query),
                    'sessions' => $this->queryService->sessions($query),
                    'funnels' => $this->queryService->funnels($config['steps'] ?? [], $query),
                    default => $this->queryService->aggregate($query),
                },
            ];
        }

        return response()->json([
            'dashboard' => $dashboard,
            'widgets' => $widgetData,
        ]);
    }

    protected function findAccessibleDashboard(string $idOrSlug, bool $customOnly = false): TelemetryDashboard
    {
        $query = TelemetryDashboard::query()
            ->where(function ($builder) use ($idOrSlug): void {
                $builder->where('id', $idOrSlug)
                    ->orWhere('slug', $idOrSlug);
            });

        if ($customOnly) {
            $query->where('is_builtin', false);
        }

        return $query->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $widgets
     */
    protected function syncWidgets(TelemetryDashboard $dashboard, array $widgets): void
    {
        $dashboard->widgets()->delete();

        foreach ($widgets as $index => $widget) {
            TelemetryDashboardWidget::query()->create([
                'dashboard_id' => $dashboard->id,
                'widget_type' => $widget['widget_type'],
                'title' => $widget['title'],
                'config' => $widget['config'],
                'position' => $widget['position'] ?? $index,
                'width' => $widget['width'] ?? 6,
                'height' => $widget['height'] ?? 2,
            ]);
        }
    }
}
