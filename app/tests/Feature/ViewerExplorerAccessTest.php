<?php

namespace Tests\Feature;

use App\Models\TelemetryApiToken;
use App\Models\TelemetryEvent;
use App\Models\TelemetryProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerExplorerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TelemetrySeeder::class);
    }

    public function test_viewer_can_access_explorer_but_not_exports(): void
    {
        $viewer = User::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@lidotelemetry.local',
            'password' => 'password123',
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $product = TelemetryProduct::query()->where('product_key', 'stox')->firstOrFail();

        TelemetryEvent::query()->create([
            'event_id' => '550e8400-e29b-41d4-a716-446655440200',
            'product_id' => $product->id,
            'environment' => 'production',
            'occurred_at' => now(),
            'received_at' => now(),
            'event_type' => 'navigation.view_started',
            'session_id' => 'sess_viewer_1',
        ]);

        $params = [
            'product_id' => $product->id,
            'environment' => 'production',
            'start' => now()->subDay()->toIso8601String(),
            'end' => now()->addHour()->toIso8601String(),
        ];

        $this->actingAs($viewer)
            ->getJson('/api/v1/explorer/sessions?'.http_build_query($params))
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson('/api/v1/explorer/views?'.http_build_query($params))
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson('/api/v1/explorer/events?'.http_build_query($params))
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson('/api/v1/exports', [
                'format' => 'json',
                'export_type' => 'raw',
                'signal_family' => 'events',
                'product_ids' => [$product->id],
                'time_range' => [
                    'start' => $params['start'],
                    'end' => $params['end'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_viewer_api_token_can_read_analytics_scopes(): void
    {
        $viewer = User::query()->create([
            'name' => 'Viewer Token User',
            'email' => 'viewer-token@lidotelemetry.local',
            'password' => 'password123',
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $product = TelemetryProduct::query()->where('product_key', 'stox')->firstOrFail();
        $rawToken = 'lt_api_viewer_test_token_'.str_repeat('x', 32);

        TelemetryApiToken::query()->create([
            'user_id' => $viewer->id,
            'name' => 'Viewer Token',
            'token_hash' => hash('sha256', $rawToken),
            'token_prefix' => substr($rawToken, 0, 16),
            'role' => 'viewer',
            'scopes' => ['analytics:read', 'events:read'],
            'product_ids' => [$product->id],
            'environment_keys' => ['production'],
            'is_active' => true,
        ]);

        $params = http_build_query([
            'product_id' => $product->id,
            'environment' => 'production',
            'start' => now()->subDay()->toIso8601String(),
            'end' => now()->addHour()->toIso8601String(),
        ]);

        $this->getJson('/api/v1/explorer/metrics?'.$params, [
            'Authorization' => 'Bearer '.$rawToken,
        ])->assertOk();

        $this->getJson('/api/v1/explorer/events?'.$params, [
            'Authorization' => 'Bearer '.$rawToken,
        ])->assertOk();
    }
}
