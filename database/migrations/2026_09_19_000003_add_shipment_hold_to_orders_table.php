<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipment hold placed when a payment deadline lapses (spec 4).
 *
 * `status` is overwritten with `payment_overdue` so the customer sees the state
 * everywhere an order status is rendered, and `status_before_hold` keeps the
 * real pipeline stage so paying (or winning an extension) puts the order back
 * exactly where it was rather than dumping it at the start of the pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status_before_hold')->nullable()->after('status');
            $table->boolean('shipment_hold')->default(false)->after('status_before_hold');
            $table->string('shipment_hold_reason', 500)->nullable()->after('shipment_hold');
            $table->timestamp('shipment_held_at')->nullable()->after('shipment_hold_reason');

            $table->index('shipment_hold');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipment_hold']);
            $table->dropColumn([
                'status_before_hold',
                'shipment_hold',
                'shipment_hold_reason',
                'shipment_held_at',
            ]);
        });
    }
};
