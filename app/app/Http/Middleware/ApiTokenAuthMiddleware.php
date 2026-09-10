<?php

namespace App\Http\Middleware;

use App\Models\TelemetryApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuthMiddleware
{
    /**
     * @param  string  ...$scopes
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $sessionUser = Auth::guard('web')->user();

        if (! $sessionUser instanceof User) {
            $sanctumUser = $request->user('sanctum');
            if ($sanctumUser instanceof User) {
                $sessionUser = $sanctumUser;
            }
        }

        if ($sessionUser instanceof User) {
            $request->setUserResolver(fn () => $sessionUser);

            return $this->authorizeSessionUser($request, $next, $scopes);
        }

        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $apiToken = TelemetryApiToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($apiToken === null) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        if (! $this->tokenHasScopes($apiToken, $scopes)) {
            return response()->json(['message' => 'Insufficient token scope.'], 403);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('telemetry_api_token', $apiToken);
        $request->attributes->set('telemetry_product_ids', $apiToken->product_ids);
        $request->attributes->set('telemetry_environment_keys', $apiToken->environment_keys);
        $request->attributes->set('telemetry_api_role', $apiToken->role);

        $request->setUserResolver(fn () => $apiToken->user);

        return $next($request);
    }

    /**
     * @param  string[]  $scopes
     */
    protected function authorizeSessionUser(Request $request, Closure $next, array $scopes): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        foreach ($scopes as $scope) {
            if (! $this->sessionUserHasScope($user, $scope)) {
                return response()->json(['message' => 'Insufficient permissions.'], 403);
            }
        }

        return $next($request);
    }

    protected function sessionUserHasScope(User $user, string $scope): bool
    {
        return match ($scope) {
            'events:read' => $user->canReadEvents(),
            'analytics:read' => $user->canReadAnalytics(),
            'exports:create' => $user->canExport(),
            'products:manage', 'credentials:manage' => $user->canManage(),
            default => false,
        };
    }

    /**
     * @param  string[]  $requiredScopes
     */
    protected function tokenHasScopes(TelemetryApiToken $token, array $requiredScopes): bool
    {
        if ($requiredScopes === []) {
            return true;
        }

        $granted = collect($token->scopes ?? []);

        if ($token->role === 'admin') {
            return true;
        }

        foreach ($requiredScopes as $scope) {
            if (! $granted->contains($scope)) {
                return false;
            }
        }

        return true;
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
