<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ticket stats group by status.
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
        });

        // Hot unread-notifications query: WHERE user_id = ? AND read_at IS NULL.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
        });

        // role_id is the leftmost column of the unique(role_id, user_id) index,
        // so the standalone index added in 2026_06_23_000003 is redundant.
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });

        // Validated as numeric; align the column type with bid_price (2026_06_23_000001).
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('out_bid_price', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->index('role_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('out_bid_price')->nullable()->change();
        });
    }
};
