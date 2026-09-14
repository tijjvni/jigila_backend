<?php

namespace Tests\Feature;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Scaling characteristics of the heavy read paths.
 *
 * Not run in CI by default — this is a measurement harness, invoked with
 * `php artisan test --filter=ScalingBenchmarkTest`. It asserts only on hard
 * ceilings that would indicate a genuine regression; the numbers it prints are
 * the point.
 */
#[Group('benchmark')]
class ScalingBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private const ROWS = 500;

    private function seedRows(int $count): User
    {
        $user = User::factory()->create(['role' => 'user']);

        $orders = [];
        $now    = now();

        for ($i = 0; $i < $count; $i++) {
            $orders[] = [
                'user_id'           => $user->id,
                'vin'               => str_pad((string) $i, 17, 'X', STR_PAD_LEFT),
                'auction_source'    => 'Copart',
                'condition'         => 'Run and Drive',
                'already_purchased' => false,
                'bid_price'         => 5000 + $i,
                'services'          => json_encode(['trucking', 'shipping']),
                'status'            => 'processing',
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }
        DB::table('orders')->insert($orders);

        $invoices = [];
        foreach (DB::table('orders')->pluck('id') as $n => $orderId) {
            $invoices[] = [
                'user_id'        => $user->id,
                'order_id'       => $orderId,
                'invoice_number' => 'INV-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'type'           => 'service',
                'description'    => 'Jigila service fee for shipment',
                'amount'         => 1200.50,
                'status'         => $n % 2 === 0 ? 'paid' : 'pending',
                'paid_at'        => $n % 2 === 0 ? $now : null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        DB::table('invoices')->insert($invoices);

        return $user;
    }

    /** @return array{ms: float, queries: int, result: mixed} */
    private function measure(callable $fn): array
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $start  = microtime(true);
        $result = $fn();

        return [
            'ms'      => round((microtime(true) - $start) * 1000, 2),
            'queries' => $queries,
            'result'  => $result,
        ];
    }

    public function test_report_read_path_scaling(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedRows(self::ROWS);

        $rows = self::ROWS;
        fwrite(STDERR, "\n──── Scaling benchmark ({$rows} orders + {$rows} invoices) ────\n");

        // 1. Unpaginated admin invoice list — the whole table, every request.
        $invoices = $this->measure(
            fn () => InvoiceResource::collection(
                Invoice::with(['user', 'order'])->latest()->get()
            )->toJson()
        );
        $bytes = strlen($invoices['result']);
        fwrite(STDERR, sprintf(
            "admin/invoices (unpaginated) : %7.2f ms | %3d queries | %s payload\n",
            $invoices['ms'],
            $invoices['queries'],
            $this->human($bytes),
        ));

        // 2. Admin dashboard — aggregation heavy.
        DB::table('cache')->delete();
        $dashboard = $this->measure(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertStatus(200)
        );
        fwrite(STDERR, sprintf(
            "admin/dashboard (cold cache) : %7.2f ms | %3d queries\n",
            $dashboard['ms'],
            $dashboard['queries'],
        ));

        // 3. Admin order list at the per_page=100 the frontend actually requests.
        $orders = $this->measure(
            fn () => $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=100')->assertStatus(200)
        );
        fwrite(STDERR, sprintf(
            "admin/orders?per_page=100    : %7.2f ms | %3d queries | %s payload\n",
            $orders['ms'],
            $orders['queries'],
            $this->human(strlen($orders['result']->getContent())),
        ));

        // 4. Config — served on nearly every page load.
        $config = $this->measure(
            fn () => $this->getJson('/api/v1/config')->assertStatus(200)
        );
        fwrite(STDERR, sprintf(
            "config                       : %7.2f ms | %3d queries | %s payload\n",
            $config['ms'],
            $config['queries'],
            $this->human(strlen($config['result']->getContent())),
        ));

        fwrite(STDERR, str_repeat('─', 62)."\n");

        // Ceilings — generous, so only a real regression trips them.
        $this->assertLessThan(60, $orders['queries'], 'Admin order list query count exploded.');
        $this->assertLessThan(60, $dashboard['queries'], 'Dashboard query count exploded.');
        $this->assertLessThan(
            5_000_000,
            $bytes,
            'Unpaginated invoice payload exceeded 5 MB — it needs server-side pagination.'
        );
    }

    private function human(int $bytes): string
    {
        return $bytes > 1_048_576
            ? round($bytes / 1_048_576, 2).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
