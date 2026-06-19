<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the 50/50 bid invoice split:
 *  - Order creation  → 50% deposit invoice (bid_deposit)
 *  - Admin marks won → 50% balance invoice (bid_balance), auto-generated once
 */
class BidInvoiceSplitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    // ─── Order creation: deposit invoice ──────────────────────────────────────

    public function test_creating_order_with_bid_price_generates_deposit_invoice(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '1HGCM82633A004352',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => false,
            'bid_price'         => '8000',
            'services'          => ['trucking'],
        ])->assertStatus(201);

        $this->assertDatabaseHas('invoices', [
            'user_id'     => $user->id,
            'type'        => 'bid_deposit',
            'description' => '50% Initial Deposit – Vehicle Auction Bid',
            'amount'      => '4000.00',
        ]);
    }

    public function test_deposit_invoice_is_exactly_50_percent_of_bid_price(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '2HGCM82633A004353',
            'auction_source'    => 'IAAI',
            'condition'         => 'Non-Runner',
            'already_purchased' => false,
            'bid_price'         => '5500',
            'services'          => [],
        ])->assertStatus(201);

        $invoice = Invoice::where('user_id', $user->id)->where('type', 'bid_deposit')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('2750.00', $invoice->amount);
    }

    public function test_no_deposit_invoice_created_when_vehicle_already_purchased(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '3HGCM82633A004354',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => true,
            'vehicle_stock_no'  => 'STK-001',
            'buyer_no'          => 'BYR-001',
            'buyer_code'        => 'BC-001',
            'services'          => [],
        ])->assertStatus(201);

        $this->assertDatabaseMissing('invoices', ['user_id' => $user->id]);
    }

    public function test_no_invoice_created_when_bid_price_is_absent(): void
    {
        // already_purchased=true with required fields means no bid_price path is taken
        $user = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '4HGCM82633A004355',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => true,
            'vehicle_stock_no'  => 'STK-002',
            'buyer_no'          => 'BYR-002',
            'buyer_code'        => 'BC-002',
            'services'          => [],
        ])->assertStatus(201);

        $this->assertDatabaseMissing('invoices', ['user_id' => $user->id]);
    }

    public function test_deposit_invoice_type_is_bid_deposit_not_bid(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '5HGCM82633A004356',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => false,
            'bid_price'         => '6000',
            'services'          => [],
        ])->assertStatus(201);

        $this->assertDatabaseMissing('invoices', ['user_id' => $user->id, 'type' => 'bid']);
        $this->assertDatabaseHas('invoices',    ['user_id' => $user->id, 'type' => 'bid_deposit']);
    }

    // ─── Admin marks bid won: balance invoice ─────────────────────────────────

    public function test_admin_marking_bid_won_generates_balance_invoice(): void
    {
        $admin  = $this->admin();
        $order  = Order::factory()->create([
            'bid_price'         => '10000',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'won',
        ])->assertStatus(200);

        $this->assertDatabaseHas('invoices', [
            'order_id'    => $order->id,
            'type'        => 'bid_balance',
            'description' => '50% Balance Payment – Auction Bid Confirmed Won',
            'amount'      => '5000.00',
        ]);
    }

    public function test_balance_invoice_is_exactly_50_percent_of_bid_price(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'bid_price'         => '7400',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'won',
        ])->assertStatus(200);

        $invoice = Invoice::where('order_id', $order->id)->where('type', 'bid_balance')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('3700.00', $invoice->amount);
    }

    public function test_balance_invoice_not_duplicated_when_bid_marked_won_twice(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'bid_price'         => '12000',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", ['bid_status' => 'won']);
        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", ['bid_status' => 'won']);

        $count = Invoice::where('order_id', $order->id)->where('type', 'bid_balance')->count();
        $this->assertEquals(1, $count);
    }

    public function test_marking_bid_lost_does_not_generate_balance_invoice(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'bid_price'         => '9000',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'lost',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('invoices', [
            'order_id' => $order->id,
            'type'     => 'bid_balance',
        ]);
    }

    public function test_marking_bid_out_bid_does_not_generate_balance_invoice(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'bid_price'         => '9000',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status'    => 'out_bid',
            'out_bid_price' => '9500',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('invoices', [
            'order_id' => $order->id,
            'type'     => 'bid_balance',
        ]);
    }

    public function test_balance_invoice_not_generated_when_order_has_no_bid_price(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['bid_price' => null]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'won',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('invoices', [
            'order_id' => $order->id,
            'type'     => 'bid_balance',
        ]);
    }

    public function test_balance_invoice_belongs_to_order_owner(): void
    {
        $admin    = $this->admin();
        $customer = $this->customer();
        $order    = Order::factory()->create([
            'user_id'           => $customer->id,
            'bid_price'         => '6000',
            'already_purchased' => false,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'won',
        ])->assertStatus(200);

        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'user_id'  => $customer->id,
            'type'     => 'bid_balance',
        ]);
    }

    public function test_non_admin_cannot_update_bid(): void
    {
        $user  = $this->customer();
        $order = Order::factory()->create(['user_id' => $user->id, 'bid_price' => '5000']);

        $this->actingAs($user)->patchJson("/api/v1/admin/orders/{$order->id}/bid", [
            'bid_status' => 'won',
        ])->assertStatus(403);
    }
}
