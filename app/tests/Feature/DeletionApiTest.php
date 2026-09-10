<?php

namespace Tests\Feature;

use App\Models\TelemetryEvent;
use App\Models\TelemetryProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TelemetrySeeder::class);
    }

    public function test_admin_can_delete_user_scoped_telemetry(): void
    {
        $admin = User::query()->where('email', 'admin@lidotelemetry.local')->firstOrFail();
        $product = TelemetryProduct::query()->where('product_key', 'stox')->firstOrFail();

        TelemetryEvent::query()->create([
            'event_id' => '550e8400-e29b-41d4-a716-446655440100',
            'product_id' => $product->id,
            'environment' => 'production',
            'occurred_at' => now(),
            'received_at' => now(),
            'event_type' => 'test.event',
            'user_id' => 'user_to_delete',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/deletions', [
                'scope_type' => 'user',
                'product_id' => $product->id,
                'scope_value' => 'user_to_delete',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['tombstone_id', 'deleted_counts']]);

        $this->assertDatabaseMissing('telemetry_events', [
            'event_id' => '550e8400-e29b-41d4-a716-446655440100',
        ]);

        $this->assertDatabaseHas('telemetry_deletion_tombstones', [
            'scope_type' => 'user',
            'scope_value' => 'user_to_delete',
        ]);

        $this->assertDatabaseHas('telemetry_audit_entries', [
            'action' => 'deletion.executed',
            'user_id' => $admin->id,
        ]);
    }
}
