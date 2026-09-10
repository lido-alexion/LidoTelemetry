<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryApiToken;
use App\Models\TelemetryIngestionCredential;
use App\Models\TelemetryProduct;
use App\Models\TelemetryProductEnvironment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listIngestionCredentials(array $filters = []): array
    {
        $query = TelemetryIngestionCredential::query()->with(['product', 'environment']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['environment_id'])) {
            $query->where('environment_id', $filters['environment_id']);
        }

        return $query->orderByDesc('created_at')->get()
            ->map(fn (TelemetryIngestionCredential $credential) => $this->ingestionCredentialPayload($credential))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createIngestionCredential(array $data, User $actor): array
    {
        $product = TelemetryProduct::query()->findOrFail($data['product_id']);
        $environment = TelemetryProductEnvironment::query()->findOrFail($data['environment_id']);

        if ($environment->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'environment_id' => ['Environment does not belong to product.'],
            ]);
        }

        $rawToken = $this->generateRawToken('lt_ing_');

        $credential = TelemetryIngestionCredential::query()->create([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
            'name' => (string) $data['name'],
            'token_hash' => $this->hashToken($rawToken),
            'token_prefix' => substr($rawToken, 0, 16),
            'is_active' => true,
        ]);

        $this->audit->log('ingestion_credential.created', $actor->id, 'ingestion_credential', (string) $credential->id);

        return [
            'credential' => $this->ingestionCredentialPayload($credential),
            'token' => $rawToken,
        ];
    }

    public function revokeIngestionCredential(int $credentialId, User $actor): void
    {
        $credential = TelemetryIngestionCredential::query()->findOrFail($credentialId);
        $credential->is_active = false;
        $credential->revoked_at = now();
        $credential->save();

        $this->audit->log('ingestion_credential.revoked', $actor->id, 'ingestion_credential', (string) $credential->id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listApiTokens(User $actor): array
    {
        return TelemetryApiToken::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TelemetryApiToken $token) => $this->apiTokenPayload($token))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createApiToken(array $data, User $actor): array
    {
        $rawToken = $this->generateRawToken('lt_api_');

        $token = TelemetryApiToken::query()->create([
            'user_id' => $actor->id,
            'name' => (string) $data['name'],
            'token_hash' => $this->hashToken($rawToken),
            'token_prefix' => substr($rawToken, 0, 16),
            'role' => (string) $data['role'],
            'scopes' => $data['scopes'],
            'product_ids' => $data['product_ids'] ?? null,
            'environment_keys' => $data['environment_keys'] ?? null,
            'is_active' => true,
            'expires_at' => isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
        ]);

        $this->audit->log('api_token.created', $actor->id, 'api_token', (string) $token->id);

        return [
            'token' => $this->apiTokenPayload($token),
            'plain_text_token' => $rawToken,
        ];
    }

    public function revokeApiToken(int $tokenId, User $actor): void
    {
        $token = TelemetryApiToken::query()
            ->where('user_id', $actor->id)
            ->where('id', $tokenId)
            ->firstOrFail();

        $token->is_active = false;
        $token->revoked_at = now();
        $token->save();

        $this->audit->log('api_token.revoked', $actor->id, 'api_token', (string) $token->id);
    }

    public function resolveIngestionCredential(string $rawToken): ?TelemetryIngestionCredential
    {
        $credential = TelemetryIngestionCredential::query()
            ->with('environment')
            ->where('token_hash', $this->hashToken($rawToken))
            ->first();

        if ($credential === null || ! $credential->isUsable()) {
            return null;
        }

        $credential->last_used_at = now();
        $credential->save();

        return $credential;
    }

    public function resolveApiToken(string $rawToken): ?TelemetryApiToken
    {
        $token = TelemetryApiToken::query()
            ->where('token_hash', $this->hashToken($rawToken))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        $token->last_used_at = now();
        $token->save();

        return $token;
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ingestionCredentialPayload(TelemetryIngestionCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'product_id' => $credential->product_id,
            'environment_id' => $credential->environment_id,
            'name' => $credential->name,
            'token_prefix' => $credential->token_prefix,
            'is_active' => $credential->is_active,
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
            'revoked_at' => $credential->revoked_at?->toIso8601String(),
            'created_at' => $credential->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function apiTokenPayload(TelemetryApiToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'token_prefix' => $token->token_prefix,
            'role' => $token->role,
            'scopes' => $token->scopes,
            'product_ids' => $token->product_ids,
            'environment_keys' => $token->environment_keys,
            'is_active' => $token->is_active,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'revoked_at' => $token->revoked_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }

    protected function generateRawToken(string $prefix): string
    {
        do {
            $token = $prefix.Str::random(48);
            $hash = $this->hashToken($token);
        } while (
            TelemetryIngestionCredential::query()->where('token_hash', $hash)->exists()
            || TelemetryApiToken::query()->where('token_hash', $hash)->exists()
        );

        return $token;
    }
}
