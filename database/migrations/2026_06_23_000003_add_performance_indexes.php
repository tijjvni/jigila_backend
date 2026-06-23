<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->index('role_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropIndex(['user_id']);
        });
    }
};
