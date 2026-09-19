<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment deadline, late fee accrual and deadline extensions (spec 4).
 *
 * `payment_due_at` is a timestamp rather than reusing `due_date`, which is a
 * `date` column with day granularity — a 72-hour clock needs hours. `due_date`
 * is still written alongside it (the date part) because the invoice email and
 * the admin invoice panel both read it.
 *
 * `late_fee_mode` / `late_fee_rate` are frozen at issuance so that changing the
 * fee schedule in settings never retroactively re-prices an invoice already in
 * the customer's hands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Deadline
            $table->timestamp('payment_due_at')->nullable()->after('due_date');
            $table->unsignedSmallInteger('deadline_hours')->nullable()->after('payment_due_at');
            $table->timestamp('overdue_at')->nullable()->after('deadline_hours');
            // Which of the "48h / 24h / 6h / at expiry" notices have gone out
            // for the current deadline. Cleared when an extension moves the
            // deadline, so the new one re-arms every stage.
            $table->json('reminder_stages_sent')->nullable()->after('reminder_count');

            // Late fees
            $table->string('late_fee_mode')->nullable()->after('reminder_stages_sent');
            $table->decimal('late_fee_rate', 8, 2)->nullable()->after('late_fee_mode');
            $table->decimal('late_fee_amount', 12, 2)->default(0)->after('late_fee_rate');
            $table->unsignedSmallInteger('late_fee_days')->default(0)->after('late_fee_amount');
            $table->timestamp('late_fee_accrued_at')->nullable()->after('late_fee_days');
            $table->timestamp('late_fee_invoiced_at')->nullable()->after('late_fee_accrued_at');

            // One-time deadline extension
            $table->string('extension_status')->nullable()->after('late_fee_invoiced_at');
            $table->unsignedSmallInteger('extension_requested_hours')->nullable()->after('extension_status');
            $table->unsignedSmallInteger('extension_granted_hours')->nullable()->after('extension_requested_hours');
            $table->string('extension_reason', 500)->nullable()->after('extension_granted_hours');
            $table->timestamp('extension_requested_at')->nullable()->after('extension_reason');
            $table->timestamp('extension_reviewed_at')->nullable()->after('extension_requested_at');
            $table->foreignId('extension_reviewed_by')->nullable()->after('extension_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('original_payment_due_at')->nullable()->after('extension_reviewed_by');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // The deadline sweeps all select on (status, payment_due_at).
            $table->index(['status', 'payment_due_at']);
            $table->index('extension_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_due_at']);
            $table->dropIndex(['extension_status']);
            $table->dropConstrainedForeignId('extension_reviewed_by');
            $table->dropColumn([
                'payment_due_at',
                'deadline_hours',
                'overdue_at',
                'reminder_stages_sent',
                'late_fee_mode',
                'late_fee_rate',
                'late_fee_amount',
                'late_fee_days',
                'late_fee_accrued_at',
                'late_fee_invoiced_at',
                'extension_status',
                'extension_requested_hours',
                'extension_granted_hours',
                'extension_reason',
                'extension_requested_at',
                'extension_reviewed_at',
                'original_payment_due_at',
            ]);
        });
    }
};
