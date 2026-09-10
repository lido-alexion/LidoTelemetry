<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\RemoteConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteConfigController extends Controller
{
    public function __construct(
        protected RemoteConfigService $remoteConfig,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $config = $this->remoteConfig->forCredentialContext(
            $request->attributes->get('telemetry_product_id'),
            $request->attributes->get('telemetry_environment_id'),
        );

        return response()->json(['data' => $config]);
    }
}
