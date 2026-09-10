<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryProductEnvironment;
use App\Models\TelemetryRemoteConfig;

class RemoteConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function forCredentialContext(string $productId, int $environmentId): array
    {
        $environment = TelemetryProductEnvironment::query()
            ->where('id', $environmentId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->first();

        if ($environment === null) {
            return $this->defaults();
        }

        $remote = TelemetryRemoteConfig::query()
            ->where('product_id', $productId)
            ->where('environment_id', $environmentId)
            ->where('is_active', true)
            ->first();

        if ($remote === null) {
            return $this->defaults();
        }

        return $this->mergeWithDefaults($remote->config ?? [], $remote->version);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveByKeys(string $productKey, string $environmentKey): array
    {
        $environment = TelemetryProductEnvironment::query()
            ->whereHas('product', fn ($query) => $query->where('product_key', $productKey)->where('is_active', true))
            ->where('environment_key', $environmentKey)
            ->where('is_active', true)
            ->first();

        if ($environment === null) {
            return $this->defaults();
        }

        return $this->forCredentialContext($environment->product_id, $environment->id);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function mergeWithDefaults(array $config, ?int $version = null): array
    {
        return array_merge($this->defaults(), [
            'version' => $version ?? 1,
            'config' => array_replace_recursive($this->defaults()['config'], $config),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'version' => 1,
            'config' => [
                'heartbeat_interval_seconds' => (int) config('telemetry.sdk.default_heartbeat_seconds', 60),
                'batch_size' => (int) config('telemetry.sdk.default_batch_size', 0),
                'automatic_navigation_tracking' => true,
                'signal_families' => [
                    'events' => true,
                    'metrics' => true,
                    'logs' => true,
                    'traces' => true,
                ],
                'metadata_policy' => [
                    'mode' => 'denylist',
                    'forbidden_keys' => config('telemetry.ingestion.forbidden_metadata_keys', []),
                ],
            ],
        ];
    }
}
