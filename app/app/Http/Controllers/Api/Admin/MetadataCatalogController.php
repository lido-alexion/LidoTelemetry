<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelemetryMetadataKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'high_cardinality' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('telemetry.query.max_limit')],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $builder = TelemetryMetadataKey::query()->orderBy('metadata_key');

        if (! empty($validated['product_id'])) {
            $builder->where('product_id', $validated['product_id']);
        }

        if (isset($validated['high_cardinality'])) {
            $builder->where('high_cardinality', $validated['high_cardinality']);
        }

        $limit = $validated['limit'] ?? config('telemetry.query.default_limit');
        $offset = $validated['offset'] ?? 0;
        $total = (clone $builder)->count();

        return response()->json([
            'data' => $builder->offset($offset)->limit($limit)->get(),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
