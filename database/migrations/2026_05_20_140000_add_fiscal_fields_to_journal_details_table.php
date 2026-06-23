<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->decimal('fiscal_amount', 15, 2)->nullable()->after('credit');
            $table->string('fiscal_note', 255)->nullable()->after('fiscal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->dropColumn(['fiscal_amount', 'fiscal_note']);
        });
    }
};
