<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment reminder bookkeeping (BUG-033) and refund processing (BUG-055).
 *
 * `last_reminded_at` + `reminder_count` let the scheduled reminder command stay
 * idempotent: it can run as often as it likes without double-sending, and it
 * stops once the configured cap is reached.
 *
 * `refund_status` is a separate axis from `status`: an invoice stays `paid`
 * while a refund is requested, approved and finally processed, so the payment
 * history is never rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('paid_at');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('last_reminded_at');

            $table->string('refund_status')->nullable()->after('reminder_count');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            $table->string('refund_reason', 500)->nullable()->after('refund_amount');
            $table->timestamp('refund_requested_at')->nullable()->after('refund_reason');
            $table->timestamp('refund_processed_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refund_processed_by')->nullable()->after('refund_processed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // The reminder sweep selects on (status, last_reminded_at).
            $table->index(['status', 'last_reminded_at']);
            $table->index('refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_reminded_at']);
            $table->dropIndex(['refund_status']);
            $table->dropConstrainedForeignId('refund_processed_by');
            $table->dropColumn([
                'last_reminded_at',
                'reminder_count',
                'refund_status',
                'refund_amount',
                'refund_reason',
                'refund_requested_at',
                'refund_processed_at',
            ]);
        });
    }
};
