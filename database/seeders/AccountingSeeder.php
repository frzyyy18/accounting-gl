<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'manager_pajak' => 'Manager Pajak',
            'admin_pajak' => 'Admin Pajak',
            'manager_internal' => 'Manager Internal',
            'omm' => 'OMM',
            'kasir_cabang' => 'Kasir Cabang',
            'auditor' => 'Auditor',
        ];

        foreach ($roles as $name => $label) {
            Role::firstOrCreate(['name' => $name], ['label' => $label, 'permissions' => []]);
        }

        Role::whereIn('name', ['admin_perusahaan', 'accounting_staff', 'finance_staff', 'viewer'])
            ->doesntHave('users')
            ->delete();

        $permissions = collect(Permission::catalog())->flatMap(function ($items, $module) {
            return collect($items)->map(fn ($item) => ['module' => $module, 'code' => $item[0], 'label' => $item[1]]);
        });

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }

        $defaultPermissions = Role::defaultPermissions();

        foreach ($defaultPermissions as $roleName => $codes) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $role->permissionRecords()->sync(Permission::whereIn('code', $codes)->pluck('id'));
            $role->update(['permissions' => $codes]);
        }

        Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah Indonesia', 'symbol' => 'Rp']);

        $company = Company::first() ?: Company::create([
            'code' => 'CN',
            'name' => 'PT Contoh Nusantara',
            'address' => 'Jakarta',
            'tax_number' => '00.000.000.0-000.000',
            'email' => 'finance@example.test',
            'phone' => '021-000000',
            'fiscal_year' => now()->year,
            'base_currency' => 'IDR',
            'is_active' => true,
        ]);

        FiscalPeriod::firstOrCreate([
            'company_id' => $company->id,
            'name' => 'Tahun Buku '.now()->year,
        ], [
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'open',
        ]);

        User::updateOrCreate(['email' => 'dwifachrezaaa@gmail.com'], [
            'name' => 'Administrator',
            'password' => 'rasta012',
            'role_id' => Role::where('name', 'super_admin')->value('id'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $accounts = [
            ['100.00', 'KAS', 'asset', null],
            ['100.01', 'Kas', 'asset', '100.00'],
            ['100.02', 'Kas Kecil', 'asset', '100.00'],
            ['100.03', 'Koin', 'asset', '100.00'],
            ['100.04', 'Kas Tagihan', 'asset', '100.00'],
            ['110.00', 'BANK', 'asset', null],
            ['110.01', 'Bank NOBU', 'asset', '110.00'],
            ['110.02', 'Bank BCA', 'asset', '110.00'],
            ['110.03', 'Bank MANDIRI', 'asset', '110.00'],
            ['110.04', 'Bank Garansi', 'asset', '110.00'],
            ['110.05', 'Bank BRI Kayuagung', 'asset', '110.00'],
            ['120.00', 'PIUTANG DAGANG', 'asset', null],
            ['120.01', 'Piutang Dagang', 'asset', '120.00'],
            ['120.02', 'Piutang Giro', 'asset', '120.00'],
            ['120.03', 'Piutang Unilever', 'asset', '120.00'],
            ['120.04', 'Piutang Unilever (Visibility)', 'asset', '120.00'],
            ['130.00', 'PERSEDIAAN BARANG DAGANG', 'asset', null],
            ['130.01', 'Persediaan Barang Dagang', 'asset', '130.00'],
            ['140.00', 'PIUTANG PAJAK', 'asset', null],
            ['140.01', 'PPN Masukan', 'asset', '140.00'],
            ['140.02', 'PPh Ps 23 (15%)', 'asset', '140.00'],
            ['140.03', 'PPh Ps 25', 'asset', '140.00'],
            ['140.04', 'PPh Ps 23 (2%)', 'asset', '140.00'],
            ['141.00', 'PIUTANG DIREKSI & KARYAWAN', 'asset', null],
            ['141.01', 'Piutang Direksi', 'asset', '141.00'],
            ['141.02', 'Piutang Karyawan', 'asset', '141.00'],
            ['145.00', 'BIAYA DIBAYAR DIMUKA', 'asset', null],
            ['145.01', 'Sewa Dibayar Dimuka', 'asset', '145.00'],
            ['145.02', 'Biaya Dibayar Dimuka Lainnya', 'asset', '145.00'],
            ['150.00', 'AKTIVA TETAP', 'asset', null],
            ['150.01', 'Kendaraan', 'asset', '150.00'],
            ['150.02', 'Inventaris Kantor', 'asset', '150.00'],
            ['151.00', 'AKUMULASI PENYUSUTAN', 'asset', null],
            ['151.01', 'Akumulasi Penyusutan', 'asset', '151.00'],
            ['200.00', 'HUTANG DAGANG', 'liability', null],
            ['200.01', 'Hutang Dagang', 'liability', '200.00'],
            ['200.02', 'Hutang Bank', 'liability', '200.00'],
            ['210.00', 'HUTANG PAJAK', 'liability', null],
            ['210.01', 'PPN Keluaran', 'liability', '210.00'],
            ['210.02', 'PPh Ps.23', 'liability', '210.00'],
            ['210.03', 'Hutang Pajak', 'liability', '210.00'],
            ['210.04', 'PPh Ps.23 (2%)', 'liability', '210.00'],
            ['220.00', 'HUTANG BANK & DIREKSI', 'liability', null],
            ['220.01', 'Hutang Bank', 'liability', '220.00'],
            ['220.02', 'Hutang Direksi', 'liability', '220.00'],
            ['250.00', 'MODAL', 'equity', null],
            ['250.01', 'Modal', 'equity', '250.00'],
            ['362.00', 'LYD', 'equity', null],
            ['362.01', 'Laba Yang Di Tahan', 'equity', '362.00'],
            ['400.00', 'PENJUALAN', 'revenue', null],
            ['400.01', 'Penjualan', 'revenue', '400.00'],
            ['400.02', 'Discount/Potongan Penjualan', 'revenue', '400.00'],
            ['450.00', 'HPP', 'expense', null],
            ['450.01', 'Pembelian', 'expense', '450.00'],
            ['460.00', 'BIAYA PENJUALAN', 'expense', null],
            ['460.01', 'Biaya Perjalanan Dinas', 'expense', '460.00'],
            ['460.02', 'Biaya Parkir', 'expense', '460.00'],
            ['460.03', 'Biaya Kend, Spare Part, Oli', 'expense', '460.00'],
            ['470.00', 'BIAYA ADMINISTRASI', 'expense', null],
            ['470.01', 'Biaya Gaji Karyawan', 'expense', '470.00'],
            ['470.02', 'Biaya Meeting', 'expense', '470.00'],
            ['470.03', 'Biaya THR Karyawan', 'expense', '470.00'],
            ['470.04', 'Biaya ATK, Percetakan, Fotocopy', 'expense', '470.00'],
            ['470.05', 'Biaya Pengiriman Surat & Benda Pos', 'expense', '470.00'],
            ['470.06', 'Biaya STNK, Pajak, KIR Mobil', 'expense', '470.00'],
            ['470.07', 'Biaya Sewa Kantor', 'expense', '470.00'],
            ['470.08', 'Biaya Perlengkapan Inventaris Kantor', 'expense', '470.00'],
            ['470.09', 'Biaya Pengiriman Barang', 'expense', '470.00'],
            ['470.10', 'Biaya Adm dan Provisi Bank', 'expense', '470.00'],
            ['470.11', 'Biaya Bunga Pinjaman Bank', 'expense', '470.00'],
            ['470.12', 'Biaya BBM', 'expense', '470.00'],
            ['470.13', 'Biaya Pajak PPh 21/PPh 25/PBB', 'expense', '470.00'],
            ['470.14', 'Biaya Keamanan & Kebersihan', 'expense', '470.00'],
            ['470.15', 'Biaya Perawatan Inventaris Kantor', 'expense', '470.00'],
            ['470.16', 'Biaya Listrik, Internet, Telpon & PAM', 'expense', '470.00'],
            ['470.17', 'Biaya Sumbangan', 'expense', '470.00'],
            ['470.21', 'Biaya Pajak Jasa Giro Bank', 'expense', '470.00'],
            ['470.22', 'Biaya Asuransi', 'expense', '470.00'],
            ['500.00', 'PENDAPATAN LAIN-LAIN', 'other_income', null],
            ['500.01', 'Bunga, Jasa Giro Bank', 'other_income', '500.00'],
            ['500.02', 'Jasa Perantara (2%)', 'other_income', '500.00'],
            ['500.03', 'Hadiah dan Penghargaan (15%)', 'other_income', '500.00'],
            ['700.00', 'PAJAK PENGHASILAN', 'other_expense', null],
            ['700.01', 'Pajak PPh Pasal 23/25/27', 'other_expense', '700.00'],
            ['700.02', 'Pajak PPh Final', 'other_expense', '700.00'],
            ['998', 'OPENING BALANCE', 'equity', null],
            ['999', 'Opening Balance', 'equity', '998'],
        ];

        foreach ($accounts as [$code, $name, $type, $parentCode]) {
            $parentId = $parentCode ? Account::where('company_id', $company->id)->where('code', $parentCode)->value('id') : null;
            Account::updateOrCreate([
                'company_id' => $company->id,
                'code' => $code,
            ], [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'is_active' => true,
            ]);
        }

        $branches = [
            ['JKT', 'Cabang Jakarta'],
            ['BDG', 'Cabang Bandung'],
        ];

        foreach ($branches as [$code, $name]) {
            $branch = Branch::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'code' => $code,
            ]);
            $branch->fill([
                'name' => $name,
                'is_active' => true,
            ]);
            $branch->deleted_at = null;
            $branch->save();
        }

        $cashAccountId = Account::where('company_id', $company->id)->where('code', '100.02')->value('id');
        $cashBank = CashBank::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'account_id' => $cashAccountId,
        ]);
        $cashBank->fill([
            'branch_id' => null,
            'scope' => 'company',
            'kind' => 'cash',
            'name' => 'Kas Kecil',
            'bank_name' => null,
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $cashBank->deleted_at = null;
        $cashBank->save();

        $cashTagihanAccountId = Account::where('company_id', $company->id)->where('code', '100.04')->value('id');
        $cashTagihan = CashBank::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'account_id' => $cashTagihanAccountId,
        ]);
        $cashTagihan->fill([
            'branch_id' => null,
            'scope' => 'company',
            'kind' => 'cash',
            'name' => 'Kas Tagihan',
            'bank_name' => null,
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $cashTagihan->deleted_at = null;
        $cashTagihan->save();

        $cashKoinAccountId = Account::where('company_id', $company->id)->where('code', '100.03')->value('id');
        $cashKoin = CashBank::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'account_id' => $cashKoinAccountId,
        ]);
        $cashKoin->fill([
            'branch_id' => null,
            'scope' => 'company',
            'kind' => 'cash',
            'name' => 'Koin',
            'bank_name' => null,
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $cashKoin->deleted_at = null;
        $cashKoin->save();

        $bankRows = [
            ['110.01', 'Bank NOBU', 'JKT'],
            ['110.02', 'Bank BCA', 'JKT'],
            ['110.03', 'Bank Mandiri', 'BDG'],
            ['110.05', 'Bank BRI Kayuagung', 'BDG'],
        ];

        foreach ($bankRows as [$accountCode, $cashBankName, $branchCode]) {
            $accountId = Account::where('company_id', $company->id)->where('code', $accountCode)->value('id');
            $branchId = Branch::where('company_id', $company->id)->where('code', $branchCode)->value('id');
            $cashBank = CashBank::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'account_id' => $accountId,
            ]);
            $cashBank->fill([
                'branch_id' => $branchId,
                'scope' => 'branch',
                'kind' => 'bank',
                'name' => $cashBankName,
                'bank_name' => $cashBankName,
                'opening_balance' => 0,
                'is_active' => true,
            ]);
            $cashBank->deleted_at = null;
            $cashBank->save();
        }
    }
}
