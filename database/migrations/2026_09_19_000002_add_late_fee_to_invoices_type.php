<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `late_fee` invoice type (spec 4).
 *
 * A late fee is billed as its own invoice rather than being added to the
 * parent: the parent's Paystack transaction was initialised for a fixed
 * amount at issuance, so a growing fee has nowhere to go on that link.
 *
 * As with the orders-status migration, this runs before the new invoice
 * columns so the SQLite rebuild happens against the simpler table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('type', ['bid', 'service', 'bid_deposit', 'bid_balance', 'late_fee'])
                ->default('bid')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('type', ['bid', 'service', 'bid_deposit', 'bid_balance'])
                ->default('bid')
                ->change();
        });
    }
};
