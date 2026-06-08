<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── User: list ───────────────────────────────────────────────────────────

    public function test_user_can_list_own_invoices(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Invoice::factory()->count(2)->create(['user_id' => $user->id]);
        Invoice::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/invoices');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_see_other_users_invoices(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Invoice::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/invoices');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_guest_cannot_list_invoices(): void
    {
        $this->getJson('/api/invoices')->assertStatus(401);
    }

    public function test_invoice_list_returns_expected_fields(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'invoice_number', 'type', 'description', 'amount', 'status', 'payment_url', 'created_at']]]);
    }

    // ─── User: show ───────────────────────────────────────────────────────────

    public function test_user_can_view_own_invoice(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $invoice->id]);
    }

    public function test_user_cannot_view_another_users_invoice(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}")->assertStatus(403);
    }

    // ─── Admin: list all ─────────────────────────────────────────────────────

    public function test_admin_can_list_all_invoices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Invoice::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/invoices');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_invoice_list_includes_all_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $u1    = User::factory()->create();
        $u2    = User::factory()->create();

        Invoice::factory()->create(['user_id' => $u1->id]);
        Invoice::factory()->create(['user_id' => $u2->id]);

        $response = $this->actingAs($admin)->getJson('/api/admin/invoices');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_non_admin_cannot_access_admin_invoices(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)->getJson('/api/admin/invoices')->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_invoices(): void
    {
        $this->getJson('/api/admin/invoices')->assertStatus(401);
    }

    // ─── Admin: generate invoice for order ───────────────────────────────────

    public function test_admin_can_generate_invoice_for_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->id}/invoices", [
            'description' => 'Shipping fee',
            'amount'      => 1500.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['description' => 'Shipping fee']);

        $this->assertDatabaseHas('invoices', [
            'order_id'    => $order->id,
            'description' => 'Shipping fee',
        ]);
    }

    public function test_generate_invoice_requires_description_and_amount(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/orders/{$order->id}/invoices", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description', 'amount']);
    }

    public function test_non_admin_cannot_generate_invoice(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/admin/orders/{$order->id}/invoices", [
                'description' => 'Fee',
                'amount'      => 500,
            ])
            ->assertStatus(403);
    }
}
