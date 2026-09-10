<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryProduct;
use App\Models\TelemetryProductEnvironment;
use App\Models\TelemetryRemoteConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductManagementService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProducts(): array
    {
        return TelemetryProduct::query()
            ->with('environments')
            ->orderBy('product_key')
            ->get()
            ->map(fn (TelemetryProduct $product) => $this->productPayload($product))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data, User $actor): TelemetryProduct
    {
        $productKey = Str::lower((string) ($data['product_key'] ?? ''));

        if ($productKey === '') {
            throw ValidationException::withMessages([
                'product_key' => ['Product key is required.'],
            ]);
        }

        $product = TelemetryProduct::query()->create([
            'product_key' => $productKey,
            'product_name' => (string) ($data['product_name'] ?? $productKey),
            'is_active' => true,
            'settings' => $data['settings'] ?? null,
        ]);

        foreach ($data['environments'] ?? [] as $environmentData) {
            $this->createEnvironmentForProduct($product, $environmentData, $actor);
        }

        $this->audit->log('product.created', $actor->id, 'product', $product->id, [
            'product_key' => $product->product_key,
        ]);

        return $product->fresh('environments');
    }

    public function getProduct(string $productRef): TelemetryProduct
    {
        return $this->resolveProduct($productRef)->load('environments');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(string $productRef, array $data, User $actor): TelemetryProduct
    {
        $product = $this->resolveProduct($productRef);

        $product->fill([
            'product_name' => $data['product_name'] ?? $product->product_name,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $product->is_active,
            'settings' => array_key_exists('settings', $data) ? $data['settings'] : $product->settings,
        ]);
        $product->save();

        $this->audit->log('product.updated', $actor->id, 'product', $product->id);

        return $product->fresh('environments');
    }

    public function deleteProduct(string $productRef, User $actor): void
    {
        $product = $this->resolveProduct($productRef);
        $product->is_active = false;
        $product->save();

        $this->audit->log('product.disabled', $actor->id, 'product', $product->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEnvironment(string $productRef, array $data, User $actor): TelemetryProductEnvironment
    {
        $product = $this->resolveProduct($productRef);

        return $this->createEnvironmentForProduct($product, $data, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEnvironment(string $productRef, int $environmentId, array $data, User $actor): TelemetryProductEnvironment
    {
        $product = $this->resolveProduct($productRef);

        $environment = TelemetryProductEnvironment::query()
            ->where('product_id', $product->id)
            ->where('id', $environmentId)
            ->firstOrFail();

        $environment->fill([
            'display_name' => $data['display_name'] ?? $environment->display_name,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $environment->is_active,
            'raw_retention_days' => $data['raw_retention_days'] ?? $environment->raw_retention_days,
            'aggregate_retention_days' => $data['aggregate_retention_days'] ?? $environment->aggregate_retention_days,
            'settings' => array_key_exists('settings', $data) ? $data['settings'] : $environment->settings,
        ]);
        $environment->save();

        $this->audit->log('environment.updated', $actor->id, 'environment', (string) $environment->id);

        return $environment->fresh();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateRemoteConfig(string $productRef, int $environmentId, array $config, User $actor): TelemetryRemoteConfig
    {
        $product = $this->resolveProduct($productRef);

        $environment = TelemetryProductEnvironment::query()
            ->where('product_id', $product->id)
            ->where('id', $environmentId)
            ->firstOrFail();

        $remoteConfig = TelemetryRemoteConfig::query()->firstOrNew([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
        ]);

        $remoteConfig->config = $config;
        $remoteConfig->version = ($remoteConfig->version ?? 0) + 1;
        $remoteConfig->is_active = true;
        $remoteConfig->save();

        $this->audit->log('remote_config.updated', $actor->id, 'remote_config', (string) $remoteConfig->id, [
            'version' => $remoteConfig->version,
        ]);

        return $remoteConfig->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createEnvironmentForProduct(TelemetryProduct $product, array $data, User $actor): TelemetryProductEnvironment
    {
        $environmentKey = Str::lower((string) ($data['environment_key'] ?? ''));

        if ($environmentKey === '') {
            throw ValidationException::withMessages([
                'environment_key' => ['Environment key is required.'],
            ]);
        }

        if (TelemetryProductEnvironment::query()
            ->where('product_id', $product->id)
            ->where('environment_key', $environmentKey)
            ->exists()) {
            throw ValidationException::withMessages([
                'environment_key' => ['Environment already exists for this product.'],
            ]);
        }

        $environment = TelemetryProductEnvironment::query()->create([
            'product_id' => $product->id,
            'environment_key' => $environmentKey,
            'display_name' => $data['display_name'] ?? $environmentKey,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'raw_retention_days' => $data['raw_retention_days'] ?? null,
            'aggregate_retention_days' => $data['aggregate_retention_days'] ?? null,
            'settings' => $data['settings'] ?? null,
        ]);

        $this->audit->log('environment.created', $actor->id, 'environment', (string) $environment->id, [
            'product_id' => $product->id,
            'environment_key' => $environmentKey,
        ]);

        return $environment;
    }

    protected function resolveProduct(string $productRef): TelemetryProduct
    {
        $product = TelemetryProduct::query()
            ->where('id', $productRef)
            ->orWhere('product_key', $productRef)
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException())->setModel(TelemetryProduct::class, [$productRef]);
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(TelemetryProduct $product): array
    {
        return [
            'id' => $product->id,
            'product_key' => $product->product_key,
            'product_name' => $product->product_name,
            'is_active' => $product->is_active,
            'settings' => $product->settings,
            'environments' => $product->environments?->map(fn (TelemetryProductEnvironment $env) => [
                'id' => $env->id,
                'environment_key' => $env->environment_key,
                'display_name' => $env->display_name,
                'is_active' => $env->is_active,
                'raw_retention_days' => $env->raw_retention_days,
                'aggregate_retention_days' => $env->aggregate_retention_days,
                'settings' => $env->settings,
            ])->all(),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
