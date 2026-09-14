<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\TestCase;

/**
 * Isolates the cost of revenueByService(), which streams every order through
 * PHP rather than aggregating in SQL. Decides whether a rewrite is warranted.
 */
#[Group('benchmark')]
class RevenueAggregationBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(int $count): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $now  = now();

        foreach (array_chunk(range(0, $count - 1), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $rows[] = [
                    'user_id'           => $user->id,
                    'vin'               => str_pad((string) $i, 17, 'X', STR_PAD_LEFT),
                    'auction_source'    => 'Copart',
                    'condition'         => 'Run and Drive',
                    'already_purchased' => false,
                    'bid_price'         => 5000 + ($i % 100),
                    'services'          => $i % 3 === 0
                        ? json_encode(['trucking'])
                        : json_encode(['trucking', 'shipping']),
                    'status'            => 'processing',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
            DB::table('orders')->insert($rows);
        }
    }

    public function test_revenue_by_service_scaling(): void
    {
        $service = app(DashboardService::class);
        $method  = (new ReflectionClass($service))->getMethod('revenueByService');
        $method->setAccessible(true);

        fwrite(STDERR, "\n──── revenueByService() scaling ────\n");

        $previous = null;
        foreach ([1_000, 5_000, 20_000] as $n) {
            DB::table('orders')->delete();
            $this->seedOrders($n);

            $start  = microtime(true);
            $result = $method->invoke($service);
            $ms     = round((microtime(true) - $start) * 1000, 2);

            fwrite(STDERR, sprintf(
                "%6d orders : %8.2f ms   (trucking %s / shipping %s)\n",
                $n,
                $ms,
                number_format($result['trucking'], 0),
                number_format($result['shipping'], 0),
            ));

            $previous = $ms;
        }

        fwrite(STDERR, str_repeat('─', 52)."\n");

        $this->assertIsFloat($previous);
    }
}
