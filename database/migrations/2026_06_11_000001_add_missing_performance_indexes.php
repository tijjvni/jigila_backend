<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('order_id');
            $table->index('status');
            $table->unique('payment_reference');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['status']);
            $table->dropUnique(['payment_reference']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
            $table->dropIndex(['user_id']);
        });
    }
};
