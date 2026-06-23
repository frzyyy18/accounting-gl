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
        Schema::create('cash_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('cash_bank_id')->index();
            $table->unsignedBigInteger('target_cash_bank_id')->nullable()->index();
            $table->unsignedBigInteger('counter_account_id')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->date('transaction_date');
            $table->enum('type', ['cash_in', 'bank_in', 'cash_out', 'transfer']);
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->enum('status', ['posted', 'cancelled'])->default('posted');
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_bank_transactions');
    }
};
