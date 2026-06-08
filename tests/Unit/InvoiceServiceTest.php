<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(?PaystackService $paystack = null): InvoiceService
    {
        $paystack ??= $this->mockPaystack();
        return new InvoiceService($paystack);
    }

    private function mockPaystack(bool $succeed = true): PaystackService
    {
        $mock = Mockery::mock(PaystackService::class);

        if ($succeed) {
            $mock->shouldReceive('initializeTransaction')
                ->andReturn([
                    'authorization_url' => 'https://paystack.com/pay/test',
                    'reference'         => 'jig_test-ref-' . uniqid(),
                ]);
        } else {
            $mock->shouldReceive('initializeTransaction')
                ->andThrow(new \RuntimeException('Paystack unavailable'));
        }

        return $mock;
    }

    // ─── create ───────────────────────────────────────────────────────────────

    public function test_create_persists_invoice_with_correct_fields(): void
    {
        $user    = User::factory()->create();
        $service = $this->makeService();

        $invoice = $service->create($user, null, 'service', 'Shipping fee', 500.00);

        $this->assertDatabaseHas('invoices', [
            'user_id'     => $user->id,
            'description' => 'Shipping fee',
            'type'        => 'service',
            'status'      => 'pending',
        ]);
        $this->assertEquals('500.00', $invoice->amount);
    }

    public function test_create_stores_paystack_url_and_reference(): void
    {
        $user    = User::factory()->create();
        $invoice = $this->makeService()->create($user, null, 'service', 'Fee', 100.00);

        $this->assertNotNull($invoice->payment_url);
        $this->assertNotNull($invoice->payment_reference);
    }

    public function test_paystack_reference_differs_from_invoice_number(): void
    {
        $user    = User::factory()->create();
        $invoice = $this->makeService()->create($user, null, 'service', 'Fee', 100.00);

        $this->assertNotEquals($invoice->invoice_number, $invoice->payment_reference);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertStringStartsWith('jig_', $invoice->payment_reference);
    }

    public function test_create_succeeds_even_when_paystack_fails(): void
    {
        $user    = User::factory()->create();
        $service = $this->makeService($this->mockPaystack(succeed: false));

        $invoice = $service->create($user, null, 'service', 'Fee', 100.00);

        $this->assertDatabaseHas('invoices', ['user_id' => $user->id, 'status' => 'pending']);
        $this->assertNull($invoice->payment_url);
        $this->assertNull($invoice->payment_reference);
    }

    // ─── list ─────────────────────────────────────────────────────────────────

    public function test_list_returns_only_the_given_users_invoices(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Invoice::factory()->count(2)->create(['user_id' => $user->id]);
        Invoice::factory()->create(['user_id' => $other->id]);

        $results = $this->makeService()->list($user);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($inv) => $inv->user_id === $user->id));
    }

    public function test_list_returns_empty_collection_when_no_invoices(): void
    {
        $user    = User::factory()->create();
        $results = $this->makeService()->list($user);

        $this->assertCount(0, $results);
    }

    // ─── listAll ──────────────────────────────────────────────────────────────

    public function test_list_all_returns_every_invoice(): void
    {
        Invoice::factory()->count(5)->create();

        $results = $this->makeService()->listAll();

        $this->assertCount(5, $results);
    }

    public function test_list_all_returns_invoices_across_multiple_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Invoice::factory()->count(2)->create(['user_id' => $u1->id]);
        Invoice::factory()->count(3)->create(['user_id' => $u2->id]);

        $results = $this->makeService()->listAll();

        $this->assertCount(5, $results);
    }

    // ─── markPaid ─────────────────────────────────────────────────────────────

    public function test_mark_paid_sets_status_and_paid_at(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $this->makeService()->markPaid($invoice, 'ref_abc123');

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_mark_paid_records_payment_reference(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $this->makeService()->markPaid($invoice, 'paystack_ref_xyz');

        $this->assertEquals('paystack_ref_xyz', $invoice->fresh()->payment_reference);
    }

    // ─── authorize ────────────────────────────────────────────────────────────

    public function test_authorize_allows_invoice_owner(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $this->makeService()->authorize($user, $invoice);

        $this->assertTrue(true);
    }

    public function test_authorize_allows_admin_regardless_of_ownership(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $other   = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id]);

        $this->makeService()->authorize($admin, $invoice);

        $this->assertTrue(true);
    }

    public function test_authorize_throws_403_for_non_owner(): void
    {
        $owner   = User::factory()->create(['role' => 'user']);
        $other   = User::factory()->create(['role' => 'user']);
        $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->makeService()->authorize($other, $invoice);
    }
}
