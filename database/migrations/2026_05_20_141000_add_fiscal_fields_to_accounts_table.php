<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('fiscal_deductibility', 5, 2)->default(100.00)->after('type');
            $table->boolean('is_non_deductible')->default(false)->after('fiscal_deductibility');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['fiscal_deductibility', 'is_non_deductible']);
        });
    }
};
