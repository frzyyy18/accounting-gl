<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cash_bank_transactions MODIFY type ENUM('cash_in', 'bank_in', 'cash_out', 'transfer') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cash_bank_transactions MODIFY type ENUM('cash_in', 'cash_out', 'transfer') NOT NULL");
        }
    }
};
