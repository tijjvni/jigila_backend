<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE invoices MODIFY COLUMN type ENUM('bid','service','bid_deposit','bid_balance') NOT NULL DEFAULT 'bid'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE invoices MODIFY COLUMN type ENUM('bid','service') NOT NULL DEFAULT 'bid'");
    }
};
