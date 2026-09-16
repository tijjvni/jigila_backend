<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vehicle sold at auction as a runner is regularly downgraded once the export
 * port inspects it — no fuel, flat tyres, dead battery. The booked `condition`
 * stays as the customer declared it; these columns record what the port
 * authority actually confirmed, so the two can be compared and the difference
 * billed without losing the original declaration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('port_condition')->nullable()->after('vehicle_type');
            $table->timestamp('port_condition_confirmed_at')->nullable()->after('port_condition');
            $table->text('port_condition_note')->nullable()->after('port_condition_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'port_condition',
                'port_condition_confirmed_at',
                'port_condition_note',
            ]);
        });
    }
};
