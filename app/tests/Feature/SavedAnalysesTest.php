<?php

namespace Tests\Feature;

use App\Models\TelemetryProduct;
use App\Models\TelemetrySavedAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedAnalysesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TelemetrySeeder::class);
    }

    public function test_analyst_can_crud_saved_analyses(): void
    {
        $analyst = User::query()->create([
            'name' => 'Analyst User',
            'email' => 'analyst@lidotelemetry.local',
            'password' => 'password123',
            'role' => 'analyst',
            'is_active' => true,
        ]);

        $product = TelemetryProduct::query()->where('product_key', 'stox')->firstOrFail();

        $createResponse = $this->actingAs($analyst)
            ->postJson('/api/v1/saved-analyses', [
                'name' => 'Top Events',
                'analysis_type' => 'group_by',
                'definition' => [
                    'signal_family' => 'events',
                    'group_by' => ['event_type'],
                    'aggregations' => [['function' => 'count']],
                ],
                'product_ids' => [$product->id],
                'environment_keys' => ['production'],
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Top Events');

        $analysisId = $createResponse->json('data.id');

        $this->actingAs($analyst)
            ->getJson('/api/v1/saved-analyses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($analyst)
            ->getJson('/api/v1/saved-analyses/'.$analysisId)
            ->assertOk()
            ->assertJsonPath('data.analysis_type', 'group_by');

        $this->actingAs($analyst)
            ->putJson('/api/v1/saved-analyses/'.$analysisId, [
                'name' => 'Updated Events',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Events');

        $this->actingAs($analyst)
            ->deleteJson('/api/v1/saved-analyses/'.$analysisId)
            ->assertOk();

        $this->assertDatabaseMissing('telemetry_saved_analyses', [
            'id' => $analysisId,
        ]);
    }

    public function test_user_cannot_access_another_users_saved_analysis(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@lidotelemetry.local',
            'password' => 'password123',
            'role' => 'analyst',
            'is_active' => true,
        ]);

        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'other@lidotelemetry.local',
            'password' => 'password123',
            'role' => 'analyst',
            'is_active' => true,
        ]);

        $product = TelemetryProduct::query()->where('product_key', 'stox')->firstOrFail();

        $analysis = TelemetrySavedAnalysis::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private Analysis',
            'analysis_type' => 'aggregate',
            'definition' => ['signal_family' => 'events'],
            'product_ids' => [$product->id],
            'environment_keys' => ['production'],
        ]);

        $this->actingAs($other)
            ->getJson('/api/v1/saved-analyses/'.$analysis->id)
            ->assertForbidden();
    }
}
