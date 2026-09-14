<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query budgets for the list endpoints.
 *
 * These are regression guards, not micro-benchmarks: each asserts that the
 * query count stays flat as the number of rows grows. An N+1 regression makes
 * the count scale with the dataset and trips the budget immediately.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Counts queries for one call.
     *
     * Callers must warm caches first — the settings cache alone accounts for a
     * one-off query on the first request of a process, which would otherwise
     * read as a phantom improvement between the small and large samples.
     *
     * @return array{0:int, 1:mixed}
     */
    private function countQueries(callable $fn): array
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $result = $fn();

        return [$queries, $result];
    }

    private function seedOrders(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order = Order::factory()->create(['user_id' => $user->id]);

            Invoice::factory()->create([
                'user_id'  => $user->id,
                'order_id' => $order->id,
                'status'   => $i % 2 === 0 ? 'paid' : 'pending',
                'paid_at'  => $i % 2 === 0 ? now() : null,
            ]);

            OrderAuditLog::create([
                'order_id'   => $order->id,
                'user_id'    => $user->id,
                'action'     => 'status_changed',
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'processing'],
            ]);
        }
    }

    public function test_admin_order_list_query_count_does_not_grow_with_row_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'user']);

        $this->seedOrders($user, 3);

        // Warm per-process caches (settings, permissions) so they are not
        // mistaken for a query saving on the second sample.
        $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=100')->assertStatus(200);

        [$small] = $this->countQueries(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=100')->assertStatus(200)
        );

        $this->seedOrders($user, 20);
        [$large] = $this->countQueries(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=100')->assertStatus(200)
        );

        $this->assertSame(
            $small,
            $large,
            "Admin order list is N+1: {$small} queries for 3 orders, {$large} for 23."
        );
    }

    public function test_customer_order_list_query_count_does_not_grow_with_row_count(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->seedOrders($user, 3);
        $this->actingAs($user)->getJson('/api/v1/orders?per_page=100')->assertStatus(200);

        [$small] = $this->countQueries(
            fn () => $this->actingAs($user)->getJson('/api/v1/orders?per_page=100')->assertStatus(200)
        );

        $this->seedOrders($user, 20);
        [$large] = $this->countQueries(
            fn () => $this->actingAs($user)->getJson('/api/v1/orders?per_page=100')->assertStatus(200)
        );

        $this->assertSame($small, $large, "Customer order list is N+1: {$small} vs {$large} queries.");
    }

    public function test_admin_invoice_list_query_count_does_not_grow_with_row_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'user']);

        $this->seedOrders($user, 3);
        $this->actingAs($admin)->getJson('/api/v1/admin/invoices')->assertStatus(200);

        [$small] = $this->countQueries(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/invoices')->assertStatus(200)
        );

        $this->seedOrders($user, 20);
        [$large] = $this->countQueries(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/invoices')->assertStatus(200)
        );

        $this->assertSame($small, $large, "Admin invoice list is N+1: {$small} vs {$large} queries.");
    }

    public function test_order_detail_with_documents_is_not_n_plus_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        foreach (range(1, 5) as $i) {
            $order->documents()->create([
                'uploaded_by'   => $admin->id,
                'type'          => 'bill_of_lading',
                'original_name' => "bol-{$i}.pdf",
                'path'          => "order-documents/{$order->id}/bol-{$i}.pdf",
                'mime_type'     => 'application/pdf',
                'size'          => 1024,
            ]);
        }

        [$queries] = $this->countQueries(
            fn () => $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/documents")->assertStatus(200)
        );

        // auth + order lookup + documents + uploaders — a handful, never 5+ per row.
        $this->assertLessThanOrEqual(
            6,
            $queries,
            "Document listing eager-loads poorly: {$queries} queries for 5 documents."
        );
    }

    public function test_dashboard_query_count_is_bounded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'user']);
        $this->seedOrders($user, 25);

        [$queries] = $this->countQueries(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertStatus(200)
        );

        $this->assertLessThanOrEqual(
            20,
            $queries,
            "Dashboard issues {$queries} queries — it should aggregate in SQL, not in PHP."
        );
    }
}
