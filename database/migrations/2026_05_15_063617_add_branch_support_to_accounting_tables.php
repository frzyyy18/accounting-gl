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
        Schema::table('cash_banks', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id')->index();
            $table->enum('scope', ['company', 'branch'])->default('company')->after('branch_id');
            $table->enum('kind', ['cash', 'bank'])->default('cash')->after('scope');
        });

        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id')->index();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id')->index();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        Schema::table('cash_banks', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'scope', 'kind']);
        });
    }
};
