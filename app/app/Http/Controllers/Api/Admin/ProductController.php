<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\ProductManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductManagementService $products,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->products->listProducts()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_key' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:telemetry_products,product_key'],
            'product_name' => ['required', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'environments' => ['nullable', 'array'],
            'environments.*.environment_key' => ['required_with:environments', 'string', 'max:64'],
            'environments.*.display_name' => ['nullable', 'string', 'max:255'],
            'environments.*.raw_retention_days' => ['nullable', 'integer', 'min:1'],
            'environments.*.aggregate_retention_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = $this->products->createProduct($validated, $request->user());

        return response()->json(['data' => $product], 201);
    }

    public function show(string $product): JsonResponse
    {
        return response()->json(['data' => $this->products->getProduct($product)]);
    }

    public function update(Request $request, string $product): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        return response()->json([
            'data' => $this->products->updateProduct($product, $validated, $request->user()),
        ]);
    }

    public function destroy(string $product): JsonResponse
    {
        $this->products->deleteProduct($product, request()->user());

        return response()->json(['message' => 'Product deleted.']);
    }

    public function storeEnvironment(Request $request, string $product): JsonResponse
    {
        $validated = $request->validate([
            'environment_key' => ['required', 'string', 'max:64'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'raw_retention_days' => ['nullable', 'integer', 'min:1'],
            'aggregate_retention_days' => ['nullable', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
        ]);

        $environment = $this->products->createEnvironment($product, $validated, $request->user());

        return response()->json(['data' => $environment], 201);
    }

    public function updateEnvironment(Request $request, string $product, int $environment): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'raw_retention_days' => ['nullable', 'integer', 'min:1'],
            'aggregate_retention_days' => ['nullable', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
        ]);

        return response()->json([
            'data' => $this->products->updateEnvironment($product, $environment, $validated, $request->user()),
        ]);
    }

    public function updateRemoteConfig(Request $request, string $product, int $environment): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        return response()->json([
            'data' => $this->products->updateRemoteConfig($product, $environment, $validated['config'], $request->user()),
        ]);
    }
}
