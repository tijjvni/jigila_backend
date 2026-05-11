<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vin');
            $table->string('stock_id')->nullable();
            $table->string('auction_source'); // Copart, IAAI, Co-parts
            $table->string('condition');       // Runner, Runs and drives, Enhanced vehicle, Stationary
            $table->boolean('already_purchased')->default(false);
            $table->string('bid_price')->nullable();
            $table->string('vehicle_stock_no')->nullable();
            $table->string('buyer_no')->nullable();
            $table->string('buyer_code')->nullable();
            $table->json('services')->nullable(); // ['trucking', 'shipping']
            $table->enum('status', ['pending', 'processing', 'in_transit', 'at_port', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
