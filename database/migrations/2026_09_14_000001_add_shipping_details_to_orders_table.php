<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping details an admin fills in once the vehicle reaches the export port
 * (BUG-034 / BUG-054): vessel identity, container, tracking, live vessel position
 * and the dates customers actually care about.
 *
 * Stored as plain strings rather than DB enums so the shipping-line and
 * shipping-type lists can grow without a schema change; the allowed values are
 * enforced by ShippingLine / ShippingType in the form request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vessel_name')->nullable()->after('destination_port');
            $table->string('container_number')->nullable()->after('vessel_name');
            $table->string('shipping_tracking_number')->nullable()->after('container_number');
            $table->string('shipping_line')->nullable()->after('shipping_tracking_number');
            $table->string('shipping_type')->nullable()->after('shipping_line');
            $table->string('current_vessel_location')->nullable()->after('shipping_type');
            $table->date('port_received_at')->nullable()->after('current_vessel_location');
            $table->date('eta_start')->nullable()->after('port_received_at');
            $table->date('eta_end')->nullable()->after('eta_start');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Customers paste a tracking number straight into the tracking box.
            $table->index('shipping_tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_tracking_number']);
            $table->dropColumn([
                'vessel_name',
                'container_number',
                'shipping_tracking_number',
                'shipping_line',
                'shipping_type',
                'current_vessel_location',
                'port_received_at',
                'eta_start',
                'eta_end',
            ]);
        });
    }
};
