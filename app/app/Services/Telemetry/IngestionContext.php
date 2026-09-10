<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryIngestionCredential;
use App\Models\TelemetryProductEnvironment;

final class IngestionContext
{
    public function __construct(
        public readonly string $productId,
        public readonly int $environmentId,
        public readonly string $environmentKey,
        public readonly int $credentialId,
    ) {}

    public static function fromCredential(TelemetryIngestionCredential $credential): self
    {
        $environment = $credential->environment ?? TelemetryProductEnvironment::query()->findOrFail($credential->environment_id);

        return new self(
            productId: $credential->product_id,
            environmentId: $credential->environment_id,
            environmentKey: $environment->environment_key,
            credentialId: $credential->id,
        );
    }
}
