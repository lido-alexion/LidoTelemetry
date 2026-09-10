<?php

use App\Http\Controllers\Api\Admin\CredentialController;
use App\Http\Controllers\Api\Admin\InviteController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExplorerController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\InviteAcceptController;
use App\Http\Controllers\Api\QueryController;
use App\Http\Controllers\Api\RemoteConfigController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/csrf-token', [AuthController::class, 'csrfToken']);
    });

    Route::get('/invites/{token}', [InviteAcceptController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::post('/invites/accept', [InviteAcceptController::class, 'accept'])
        ->middleware('throttle:60,1');

    Route::middleware('ingestion.auth')->group(function (): void {
        Route::get('/remote-config', [RemoteConfigController::class, 'show']);

        Route::prefix('ingest')->group(function (): void {
            Route::post('/events', [IngestionController::class, 'events']);
            Route::post('/metrics', [IngestionController::class, 'metrics']);
            Route::post('/logs', [IngestionController::class, 'logs']);
            Route::post('/traces', [IngestionController::class, 'traces']);
            Route::post('/otel', [IngestionController::class, 'otel']);
        });
    });

    Route::middleware('api.token:analytics:read')->group(function (): void {
        Route::post('/query', QueryController::class);

        Route::prefix('explorer')->group(function (): void {
            Route::get('/events', [ExplorerController::class, 'events']);
            Route::get('/metrics', [ExplorerController::class, 'metrics']);
            Route::get('/logs', [ExplorerController::class, 'logs']);
            Route::get('/traces', [ExplorerController::class, 'traces']);
            Route::get('/sessions', [ExplorerController::class, 'sessions']);
        });
    });

    Route::middleware('api.token:exports:create')->group(function (): void {
        Route::post('/exports', [ExportController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'telemetry.active'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboards', [DashboardController::class, 'index']);
        Route::post('/dashboards', [DashboardController::class, 'store']);
        Route::get('/dashboards/builtin/{slug}/data', [DashboardController::class, 'builtinData']);
        Route::get('/dashboards/{dashboard}', [DashboardController::class, 'show']);
        Route::put('/dashboards/{dashboard}', [DashboardController::class, 'update']);
        Route::delete('/dashboards/{dashboard}', [DashboardController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'telemetry.active', 'telemetry.admin'])->prefix('admin')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/environments', [ProductController::class, 'storeEnvironment']);
        Route::put('/products/{product}/environments/{environment}', [ProductController::class, 'updateEnvironment']);
        Route::put('/products/{product}/environments/{environment}/remote-config', [ProductController::class, 'updateRemoteConfig']);

        Route::get('/credentials/ingestion', [CredentialController::class, 'indexIngestion']);
        Route::post('/credentials/ingestion', [CredentialController::class, 'storeIngestion']);
        Route::delete('/credentials/ingestion/{credential}', [CredentialController::class, 'revokeIngestion']);

        Route::get('/credentials/api-tokens', [CredentialController::class, 'indexApiTokens']);
        Route::post('/credentials/api-tokens', [CredentialController::class, 'storeApiToken']);
        Route::delete('/credentials/api-tokens/{token}', [CredentialController::class, 'revokeApiToken']);

        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{user}', [UserController::class, 'update']);

        Route::get('/invites', [InviteController::class, 'index']);
        Route::post('/invites', [InviteController::class, 'store']);
        Route::post('/invites/{invite}/regenerate', [InviteController::class, 'regenerate']);
        Route::delete('/invites/{invite}', [InviteController::class, 'destroy']);
    });
});
