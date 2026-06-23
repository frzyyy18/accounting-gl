<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashBank;
use App\Support\CashBankMutation;
use Illuminate\Http\Request;

class CashBankController extends Controller
{
    public function index()
    {
        $this->denyViewer();

        $cashBanks = CashBank::with('account', 'branch')
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('name')
            ->paginate(15);

        return view('cash-banks.index', compact('cashBanks'));
    }

    public function create()
    {
        $this->denyReadOnly();

        return view('cash-banks.form', [
            'cashBank' => new CashBank(['is_active' => true]),
            'accounts' => $this->assetAccounts(),
            'branches' => $this->branches(),
        ]);
    }

    public function store(Request $request)
    {
        $this->denyReadOnly();
        $cashBank = CashBank::create($this->validated($request));
        AuditLog::record('create', 'Cash & Bank', null, $cashBank->toArray(), $cashBank->company_id);

        return redirect()->route('cash-banks.index')->with('success', 'Kas/Bank berhasil dibuat.');
    }

    public function show(CashBank $cashBank)
    {
        $this->authorizeCompany($cashBank->company_id);
        $scopeCashBanks = collect([$cashBank]);
        $balances = CashBankMutation::openingBalances($cashBank->company_id, $scopeCashBanks);
        $rows = CashBankMutation::rows($cashBank->company_id, $scopeCashBanks);
        $mutationRows = CashBankMutation::withRunningBalances($rows, $balances)->reverse()->values();

        return view('cash-banks.show', [
            'cashBank' => $cashBank->load('account', 'branch'),
            'mutationRows' => $mutationRows,
        ]);
    }

    public function edit(CashBank $cashBank)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($cashBank->company_id);

        return view('cash-banks.form', [
            'cashBank' => $cashBank,
            'accounts' => $this->assetAccounts(),
            'branches' => $this->branches(),
        ]);
    }

    public function update(Request $request, CashBank $cashBank)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($cashBank->company_id);
        $old = $cashBank->toArray();
        $cashBank->update($this->validated($request, $cashBank));
        AuditLog::record('update', 'Cash & Bank', $old, $cashBank->fresh()->toArray(), $cashBank->company_id);

        return redirect()->route('cash-banks.index')->with('success', 'Kas/Bank berhasil diperbarui.');
    }

    public function destroy(CashBank $cashBank)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($cashBank->company_id);

        if ($cashBank->transactions()->exists() || $cashBank->targetTransactions()->exists()) {
            return back()->withErrors('Kas/Bank yang sudah memiliki mutasi tidak boleh dihapus.');
        }

        $old = $cashBank->toArray();
        $cashBank->delete();
        AuditLog::record('delete', 'Cash & Bank', $old, null, $cashBank->company_id);

        return back()->with('success', 'Kas/Bank berhasil dihapus.');
    }

    private function validated(Request $request, ?CashBank $cashBank = null): array
    {
        $companyId = $cashBank?->company_id ?? auth()->user()->company_id;
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'scope' => ['required', 'in:company,branch'],
            'kind' => ['required', 'in:cash,bank'],
            'branch_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->findOrFail($data['account_id']);

        if ($data['kind'] === 'bank') {
            $data['scope'] = 'branch';
        }

        if ($data['scope'] === 'branch') {
            $data['branch_id'] = Branch::where('company_id', $companyId)
                ->where('is_active', true)
                ->findOrFail($data['branch_id'])->id;
        } else {
            $data['branch_id'] = null;
        }

        $data['company_id'] = $companyId;
        $data['account_id'] = $account->id;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function assetAccounts()
    {
        return Account::where('company_id', auth()->user()->company_id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function branches()
    {
        return Branch::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function authorizeCompany(int $companyId): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $companyId, 403);
    }

    private function denyViewer(): void
    {
        abort_unless(auth()->user()->canManage('cash_bank.view'), 403, 'Anda tidak memiliki hak akses kas/bank.');
    }

    private function denyReadOnly(): void
    {
        abort_if(auth()->user()->hasRole('auditor'), 403, 'Role ini hanya boleh membaca data.');
        abort_unless(auth()->user()->canManage('cash_bank.manage'), 403, 'Anda tidak memiliki hak akses mengelola kas/bank.');
    }
}
