<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_reconciliation_id')->nullable()->after('journal_entry_id')->index();
            $table->boolean('is_reconciled')->default(false)->after('status');
            $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->dropColumn(['bank_reconciliation_id', 'is_reconciled', 'reconciled_at']);
        });
    }
};
