<?php

namespace Tests\Feature;

use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** @group benchmark */
#[Group('benchmark')]
class InvoicePayloadProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_invoice_row_composition(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        // A realistic PAID invoice: InvoiceService stores creation context plus
        // a ~15-field Paystack payment blob under metadata.payment.
        Invoice::factory()->create([
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => 'paid',
            'paid_at'  => now(),
            'metadata' => [
                'exchange_rate' => 1600, 'amount_usd' => 1200.5, 'amount_ngn' => 1920800,
                'amount_kobo' => 192080000, 'invoice_type' => 'service', 'user_id' => $user->id,
                'user_email' => $user->email, 'order_id' => $order->id, 'order_vin' => $order->vin,
                'auction_source' => 'Copart', 'condition' => 'Run and Drive',
                'payment' => [
                    'transaction_id' => 4099260516, 'domain' => 'live', 'channel' => 'card',
                    'currency' => 'NGN', 'amount_paid_kobo' => 192080000, 'fees_kobo' => 2881200,
                    'ip_address' => '102.89.23.11', 'gateway_response' => 'Successful',
                    'paystack_paid_at' => '2026-07-02T10:15:22.000Z', 'auth_bank' => 'Guaranty Trust Bank',
                    'auth_last4' => '4081', 'auth_card_type' => 'visa DEBIT', 'auth_brand' => 'visa',
                    'auth_country' => 'NG', 'customer_code' => 'CUS_xxxxxxxxxxxxxx',
                ],
            ],
        ]);

        $this->actingAs($admin);
        $invoice = Invoice::with(['user', 'order'])->firstOrFail();

        // The two representations as the API actually serves them: the detail
        // endpoint carries `metadata`, the list endpoint does not.
        $detail = strlen(InvoiceDetailResource::make($invoice)->toJson());
        $list   = strlen(InvoiceResource::make($invoice)->toJson());

        fwrite(STDERR, sprintf(
            "\n──── one PAID invoice, admin view ────\n".
            "detail row (with metadata) : %5d B\n".
            "list row   (no metadata)   : %5d B\n".
            "metadata share of detail   : %5.1f%%\n".
            "a 5,000-row list           : %.2f MB  (was %.2f MB)\n".
            "──────────────────────────────────────\n",
            $detail, $list, ($detail - $list) / $detail * 100,
            $list * 5000 / 1048576, $detail * 5000 / 1048576,
        ));

        $this->assertGreaterThan(
            $list,
            $detail,
            'Detail representation must carry metadata that the list omits.'
        );
    }
}
