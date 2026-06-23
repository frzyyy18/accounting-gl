<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->selectedCompanyId($request);
        $branches = Branch::with('company')
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->paginate(15);

        return view('branches.index', [
            'branches' => $branches,
            'companies' => $this->companies(),
            'selectedCompanyId' => $companyId,
        ]);
    }

    public function create()
    {
        return view('branches.form', [
            'branch' => new Branch([
                'company_id' => $this->selectedCompanyId(request()),
                'is_active' => true,
            ]),
            'companies' => $this->companies(),
        ]);
    }

    public function store(Request $request)
    {
        $branch = Branch::create($this->validated($request));
        AuditLog::record('create', 'Branch', null, $branch->toArray(), $branch->company_id, $branch->id);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil dibuat.');
    }

    public function show(Branch $branch)
    {
        return redirect()->route('branches.edit', $branch);
    }

    public function edit(Branch $branch)
    {
        $this->authorizeCompany($branch->company_id);

        return view('branches.form', [
            'branch' => $branch,
            'companies' => $this->companies(),
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeCompany($branch->company_id);
        $old = $branch->toArray();
        $branch->update($this->validated($request, $branch));
        AuditLog::record('update', 'Branch', $old, $branch->fresh()->toArray(), $branch->company_id, $branch->id);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil diperbarui.');
    }

    public function destroy(Branch $branch)
    {
        $this->authorizeCompany($branch->company_id);

        if ($branch->journals()->exists()) {
            return back()->withErrors('Cabang yang sudah dipakai transaksi/jurnal tidak boleh dihapus.');
        }

        $old = $branch->toArray();
        DB::transaction(function () use ($branch, $old) {
            $branch->cashBanks()->delete();
            $branch->delete();
            AuditLog::record('delete', 'Branch', $old, null, $branch->company_id, $branch->id);
        });

        return back()->with('success', 'Cabang berhasil dihapus.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $companyId = $this->validatedCompanyId($request, $branch);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branches')->where('company_id', $companyId)->ignore($branch?->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['company_id'] = $companyId;
        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validatedCompanyId(Request $request, ?Branch $branch = null): int
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return auth()->user()->company_id;
        }

        $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        if ($branch && $branch->company_id !== (int) $request->input('company_id') && $branch->journals()->exists()) {
            abort(422, 'Cabang yang sudah memiliki transaksi tidak boleh dipindah perusahaan.');
        }

        return (int) $request->input('company_id');
    }

    private function selectedCompanyId(Request $request): int
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return auth()->user()->company_id;
        }

        $requested = $request->integer('company_id');
        if ($requested && Company::whereKey($requested)->exists()) {
            return $requested;
        }

        return auth()->user()->company_id ?: (int) Company::orderBy('name')->value('id');
    }

    private function companies()
    {
        return Company::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }

    private function authorizeCompany(int $companyId): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $companyId, 403);
    }
}
