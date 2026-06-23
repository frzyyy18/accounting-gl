<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'settings.manage'],
            [
                'module' => 'Pengaturan',
                'label' => 'Kelola Pengaturan Sistem',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permission_role')
            ->whereIn('permission_id', DB::table('permissions')->where('code', 'settings.manage')->pluck('id'))
            ->delete();

        DB::table('permissions')->where('code', 'settings.manage')->delete();
    }
};
