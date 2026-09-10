<?php

namespace App\Http\Middleware;

use App\Models\TelemetryIngestionCredential;
use App\Models\TelemetryProductEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IngestionAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return response()->json(['message' => 'Ingestion token required.'], 401);
        }

        $credential = TelemetryIngestionCredential::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($credential === null) {
            return response()->json(['message' => 'Invalid ingestion token.'], 401);
        }

        $environment = TelemetryProductEnvironment::query()
            ->where('id', $credential->environment_id)
            ->where('is_active', true)
            ->first();

        if ($environment === null) {
            return response()->json(['message' => 'Ingestion environment is inactive.'], 403);
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('telemetry_product_id', $credential->product_id);
        $request->attributes->set('telemetry_environment', $environment->environment_key);
        $request->attributes->set('telemetry_environment_id', $environment->id);
        $request->attributes->set('telemetry_ingestion_credential', $credential);

        return $next($request);
    }

    protected function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
