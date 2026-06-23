<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Account;
use App\Models\Company;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('id', auth()->user()->company_id))
            ->latest()
            ->paginate(15);

        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        $this->authorizeSuperAdmin();

        return view('companies.form', ['company' => new Company]);
    }

    public function store(Request $request)
    {
        $this->authorizeSuperAdmin();

        $company = DB::transaction(function () use ($request) {
            $company = Company::create($this->validated($request));
            $this->copyChartOfAccounts($company);
            $this->saveTaxSettings($request);
            AuditLog::record('create', 'Company', null, $company->toArray(), $company->id);

            return $company;
        });

        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil dibuat.');
    }

    public function show(Company $company)
    {
        $this->authorizeCompany($company);

        return redirect()->route('companies.edit', $company);
    }

    public function edit(Company $company)
    {
        $this->authorizeCompany($company);

        return view('companies.form', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $old = $company->toArray();
        $company->update($this->validated($request));
        $this->saveTaxSettings($request);
        AuditLog::record('update', 'Company', $old, $company->fresh()->toArray(), $company->id);

        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil diperbarui.');
    }

    public function destroy(Company $company)
    {
        $this->authorizeSuperAdmin();

        if ($company->journals()->exists()) {
            return back()->withErrors('Perusahaan yang sudah memiliki transaksi/jurnal tidak boleh dihapus.');
        }

        $old = $company->toArray();
        DB::transaction(function () use ($company, $old) {
            $company->cashBanks()->delete();
            $company->branches()->delete();
            $company->fiscalPeriods()->delete();
            $company->accounts()->delete();
            $company->delete();
            AuditLog::record('delete', 'Company', $old, null, $company->id);
        });

        return back()->with('success', 'Perusahaan berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('companies')->ignore($request->route('company')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'base_currency' => ['required', 'string', 'size:3'],
            'tax_rate_corporate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['tax_rate_corporate']);

        return $data;
    }

    private function saveTaxSettings(Request $request): void
    {
        if (! auth()->user()->hasRole('super_admin') || ! $request->filled('tax_rate_corporate')) {
            return;
        }

        SystemSetting::updateOrCreate(
            ['key' => 'tax_rate_corporate'],
            [
                'value' => (string) (float) $request->input('tax_rate_corporate'),
                'description' => 'Tarif PPh Badan (%) - default 22%',
            ]
        );

        corporateTaxRate(true);
    }

    private function copyChartOfAccounts(Company $company): void
    {
        if ($company->accounts()->exists()) {
            return;
        }

        $templateCompanyId = Account::query()
            ->select('company_id')
            ->where('company_id', '!=', $company->id)
            ->groupBy('company_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('company_id');

        if (! $templateCompanyId) {
            return;
        }

        $templateAccounts = Account::where('company_id', $templateCompanyId)
            ->orderBy('code')
            ->get();

        $accountIdMap = [];

        foreach ($templateAccounts as $templateAccount) {
            $account = Account::create([
                'company_id' => $company->id,
                'parent_id' => $templateAccount->parent_id ? ($accountIdMap[$templateAccount->parent_id] ?? null) : null,
                'code' => $templateAccount->code,
                'name' => $templateAccount->name,
                'type' => $templateAccount->type,
                'fiscal_deductibility' => $templateAccount->fiscal_deductibility,
                'is_non_deductible' => $templateAccount->is_non_deductible,
                'tax_category' => $templateAccount->tax_category,
                'is_active' => $templateAccount->is_active,
            ]);

            $accountIdMap[$templateAccount->id] = $account->id;
        }
    }

    private function authorizeCompany(Company $company): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $company->id, 403);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Hanya Super Admin yang boleh membuat atau menghapus perusahaan.');
    }
}
