<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payment_overdue` order status (spec 4).
 *
 * Runs before the column-adding migrations in this batch on purpose: on SQLite
 * a `->change()` rebuilds the whole table, and a rebuild is far safer while the
 * table still has no extra foreign keys hanging off it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'processing', 'pickup',
                'in_transit', 'at_port', 'on_vessel', 'delivered', 'cancelled',
                'payment_overdue',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'processing', 'pickup',
                'in_transit', 'at_port', 'on_vessel', 'delivered', 'cancelled',
            ])->default('pending')->change();
        });
    }
};
