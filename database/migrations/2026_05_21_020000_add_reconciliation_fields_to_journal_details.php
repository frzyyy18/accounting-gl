<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_reconciliation_id')->nullable()->after('journal_entry_id')->index();
            $table->boolean('is_reconciled')->default(false)->after('fiscal_note');
            $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
        });
    }

    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->dropColumn(['bank_reconciliation_id', 'is_reconciled', 'reconciled_at']);
        });
    }
};
