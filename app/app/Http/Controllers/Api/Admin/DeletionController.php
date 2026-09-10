<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\DeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeletionController extends Controller
{
    public function __construct(
        protected DeletionService $deletions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'string', 'in:product,environment,user,anonymous,session,range'],
            'product_id' => ['nullable', 'uuid'],
            'environment' => ['nullable', 'string', 'max:64'],
            'scope_value' => ['nullable', 'string', 'max:255'],
            'range_start' => ['nullable', 'date'],
            'range_end' => ['nullable', 'date', 'after:range_start'],
        ]);

        $result = $this->deletions->deleteByScope(
            $validated['scope_type'],
            $validated,
            $request->user()?->id,
        );

        return response()->json(['data' => $result], 201);
    }
}
