<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL was already patched by the 2026_06_15_120000 migration.
        // SQLite doesn't support ALTER TABLE MODIFY COLUMN, so we recreate
        // the invoices table with the correct CHECK constraint.
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        // Rename old table — indexes keep their original names in SQLite,
        // so we must drop the unique index before creating the new table.
        DB::statement('ALTER TABLE invoices RENAME TO invoices_old_type_fix');
        DB::statement('DROP INDEX IF EXISTS invoices_invoice_number_unique');

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->enum('type', ['bid', 'service', 'bid_deposit', 'bid_balance'])->default('bid');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO invoices
                (id, user_id, order_id, invoice_number, type, description, amount,
                 status, due_date, paid_at, payment_reference, payment_url, metadata,
                 created_at, updated_at)
            SELECT
                id, user_id, order_id, invoice_number, type, description, amount,
                status, due_date, paid_at, payment_reference, payment_url, metadata,
                created_at, updated_at
            FROM invoices_old_type_fix
        ');

        DB::statement('DROP TABLE invoices_old_type_fix');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Not reversible — the prior state had an incomplete enum constraint.
    }
};
