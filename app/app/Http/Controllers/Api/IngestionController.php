<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\IngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestionController extends Controller
{
    public function __construct(
        protected IngestionService $ingestion,
    ) {}

    public function events(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['array'],
        ]);

        $result = $this->ingestion->ingestEvents(
            $payload['events'],
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment'),
        );

        return response()->json($result, 202);
    }

    public function metrics(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['array'],
        ]);

        $result = $this->ingestion->ingestMetrics(
            $payload['metrics'],
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment'),
        );

        return response()->json($result, 202);
    }

    public function logs(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'logs' => ['required', 'array', 'min:1'],
            'logs.*' => ['array'],
        ]);

        $result = $this->ingestion->ingestLogs(
            $payload['logs'],
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment'),
        );

        return response()->json($result, 202);
    }

    public function traces(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'spans' => ['required', 'array', 'min:1'],
            'spans.*' => ['array'],
        ]);

        $result = $this->ingestion->ingestTraces(
            $payload['spans'],
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment'),
        );

        return response()->json($result, 202);
    }

    public function otel(Request $request): JsonResponse
    {
        $payload = $request->all();

        $result = $this->ingestion->ingestOtelBatch(
            $payload,
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment'),
        );

        return response()->json($result, 202);
    }
}
