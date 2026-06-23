<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id')->index();
        });

        DB::table('companies')->orderBy('id')->get(['id', 'name'])->each(function ($company) {
            DB::table('companies')
                ->where('id', $company->id)
                ->update(['code' => $this->companyCode($company->name, $company->id)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    private function companyCode(string $name, int $id): string
    {
        $words = preg_split('/\s+/', preg_replace('/\b(PT|CV|TBK|UD)\b/i', '', $name) ?: '', -1, PREG_SPLIT_NO_EMPTY);
        $code = collect($words)->map(fn ($word) => substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($word)), 0, 1))->join('');

        return substr($code ?: 'COMP'.$id, 0, 20);
    }
};
