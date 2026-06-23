<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccountingSeeder::class);

        SystemSetting::firstOrCreate(
            ['key' => 'tax_rate_corporate'],
            ['value' => '22', 'description' => 'Tarif PPh Badan (%) - default 22%']
        );
    }
}
