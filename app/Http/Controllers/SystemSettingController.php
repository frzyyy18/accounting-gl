<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function edit()
    {
        return view('system-settings.edit', [
            'taxRateCorporate' => corporateTaxRate() * 100,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'tax_rate_corporate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $old = SystemSetting::where('key', 'tax_rate_corporate')->first()?->toArray();
        $setting = SystemSetting::updateOrCreate(
            ['key' => 'tax_rate_corporate'],
            [
                'value' => (string) (float) $data['tax_rate_corporate'],
                'description' => 'Tarif PPh Badan (%) - default 22%',
            ]
        );

        corporateTaxRate(true);

        AuditLog::record('update', 'System Settings', $old, $setting->fresh()->toArray());

        return back()->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }
}
