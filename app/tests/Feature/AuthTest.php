<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TelemetrySeeder::class);
    }

    public function test_admin_can_login_and_fetch_me(): void
    {
        $this->startSession();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lidotelemetry.local',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@lidotelemetry.local');
    }

    public function test_invalid_login_returns_422(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lidotelemetry.local',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_admin_can_list_products(): void
    {
        $admin = User::query()->where('email', 'admin@lidotelemetry.local')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonFragment(['product_key' => 'stox']);
    }
}
