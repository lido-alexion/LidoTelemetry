<?php

namespace Tests\Feature;

use App\Models\TelemetryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestionTest extends TestCase
{
    use RefreshDatabase;

    protected string $ingestionToken = 'lti_demo_stox_production_seed_token_change_me';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TelemetrySeeder::class);
    }

    public function test_events_ingestion_accepts_valid_payload(): void
    {
        $response = $this->postJson('/api/v1/ingest/events', [
            'events' => [[
                'event_id' => '550e8400-e29b-41d4-a716-446655440000',
                'event_type' => 'navigation.view_started',
                'occurred_at' => now()->toIso8601String(),
                'session_id' => 'sess_test_1',
                'view_instance_id' => 'view_test_1',
                'metadata' => ['view_name' => 'dashboard'],
            ]],
        ], [
            'Authorization' => 'Bearer '.$this->ingestionToken,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('telemetry_events', [
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'event_type' => 'navigation.view_started',
        ]);
    }

    public function test_duplicate_event_is_deduped(): void
    {
        $payload = [
            'events' => [[
                'event_id' => '550e8400-e29b-41d4-a716-446655440001',
                'event_type' => 'interaction.button_clicked',
                'occurred_at' => now()->toIso8601String(),
                'metadata' => ['button' => 'save'],
            ]],
        ];

        $this->postJson('/api/v1/ingest/events', $payload, [
            'Authorization' => 'Bearer '.$this->ingestionToken,
        ])->assertAccepted();

        $this->postJson('/api/v1/ingest/events', $payload, [
            'Authorization' => 'Bearer '.$this->ingestionToken,
        ])->assertAccepted()->assertJsonPath('duplicates', 1);

        $this->assertEquals(1, \App\Models\TelemetryEvent::query()->count());
    }

    public function test_ingestion_rejects_invalid_token(): void
    {
        $this->postJson('/api/v1/ingest/events', [
            'events' => [[
                'event_id' => '550e8400-e29b-41d4-a716-446655440002',
                'event_type' => 'test.event',
                'occurred_at' => now()->toIso8601String(),
            ]],
        ], [
            'Authorization' => 'Bearer invalid',
        ])->assertUnauthorized();
    }

    public function test_metrics_ingestion(): void
    {
        $this->postJson('/api/v1/ingest/metrics', [
            'metrics' => [[
                'id' => '650e8400-e29b-41d4-a716-446655440000',
                'name' => 'api.latency_ms',
                'type' => 'timer',
                'value' => 42.5,
                'occurred_at' => now()->toIso8601String(),
            ]],
        ], [
            'Authorization' => 'Bearer '.$this->ingestionToken,
        ])->assertAccepted();

        $this->assertDatabaseHas('telemetry_metrics', [
            'name' => 'api.latency_ms',
        ]);
    }

    public function test_remote_config_returns_json(): void
    {
        $this->getJson('/api/v1/remote-config', [
            'Authorization' => 'Bearer '.$this->ingestionToken,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['config' => ['heartbeat_interval_seconds', 'batch_size']]]);
    }
}
