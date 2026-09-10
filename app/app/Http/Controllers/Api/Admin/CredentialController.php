<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(
        protected CredentialService $credentials,
    ) {}

    public function indexIngestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'environment_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'data' => $this->credentials->listIngestionCredentials($validated),
        ]);
    }

    public function storeIngestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid'],
            'environment_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->credentials->createIngestionCredential($validated, $request->user());

        return response()->json(['data' => $result], 201);
    }

    public function revokeIngestion(Request $request, int $credential): JsonResponse
    {
        $this->credentials->revokeIngestionCredential($credential, $request->user());

        return response()->json(['message' => 'Ingestion credential revoked.']);
    }

    public function indexApiTokens(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->credentials->listApiTokens($request->user()),
        ]);
    }

    public function storeApiToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:admin,analyst,viewer'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', config('telemetry.scopes'))],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['uuid'],
            'environment_keys' => ['nullable', 'array'],
            'environment_keys.*' => ['string', 'max:64'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $result = $this->credentials->createApiToken($validated, $request->user());

        return response()->json(['data' => $result], 201);
    }

    public function revokeApiToken(Request $request, int $token): JsonResponse
    {
        $this->credentials->revokeApiToken($token, $request->user());

        return response()->json(['message' => 'API token revoked.']);
    }
}
