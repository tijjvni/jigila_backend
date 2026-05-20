<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_location')->nullable()->after('status');
            $table->string('departure_port')->nullable()->after('pickup_location');
            $table->string('destination_port')->nullable()->after('departure_port');
            $table->enum('bid_status', ['pending', 'won', 'lost', 'out_bid'])->nullable()->after('destination_port');
            $table->string('out_bid_price')->nullable()->after('bid_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_location', 'departure_port', 'destination_port', 'bid_status', 'out_bid_price']);
        });
    }
};
