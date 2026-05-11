<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $users   = User::factory()->count(3)->create();
        Order::factory()->count(5)->create(['user_id' => $users->first()->id]);

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'total_orders',
                    'orders_by_status',
                    'recent_orders',
                ],
            ])
            ->assertJsonPath('data.total_users', 3)
            ->assertJsonPath('data.total_orders', 5);
    }

    public function test_regular_user_cannot_view_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_guest_cannot_view_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    public function test_dashboard_recent_orders_are_limited_to_ten(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create();
        Order::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

        $this->assertCount(10, $response->json('data.recent_orders'));
    }
}
