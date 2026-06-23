<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $this->denyViewer();
        $companyId = auth()->user()->company_id;
        $sorts = [
            'code' => 'code',
            'name' => 'name',
            'type' => 'type',
            'parent' => 'parent_id',
            'tax_category' => 'tax_category',
            'status' => 'is_active',
        ];
        $sort = $sorts[$request->input('sort')] ?? 'code';
        $direction = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $accounts = Account::with('parent')
            ->where('company_id', $companyId)
            ->when($request->search, fn ($q) => $q->where(fn ($qq) => $qq->where('code', 'like', "%{$request->search}%")->orWhere('name', 'like', "%{$request->search}%")))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->tax_category === 'none', fn ($q) => $q->whereNull('tax_category'))
            ->when($request->tax_category && $request->tax_category !== 'none', fn ($q) => $q->where('tax_category', $request->tax_category))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        $accountDepths = $this->accountDepths($companyId);

        return view('accounts.index', [
            'accounts' => $accounts,
            'accountDepths' => $accountDepths,
            'types' => Account::TYPES,
            'taxCategories' => Account::TAX_CATEGORIES,
        ]);
    }

    public function create()
    {
        $this->denyReadOnly();
        return view('accounts.form', [
            'account' => new Account(['is_active' => true]),
            'accounts' => $this->accountOptions(),
            'types' => Account::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $this->denyReadOnly();
        $data = $this->validated($request);
        $account = Account::create($data);
        AuditLog::record('create', 'Chart of Account', null, $account->toArray(), $account->company_id);

        return redirect()->route('accounts.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function show(Account $account)
    {
        return redirect()->route('accounts.edit', $account);
    }

    public function edit(Account $account)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($account->company_id);

        return view('accounts.form', [
            'account' => $account,
            'accounts' => $this->accountOptions($account->id),
            'types' => Account::TYPES,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($account->company_id);
        $old = $account->toArray();
        $account->update($this->validated($request, $account));
        AuditLog::record('update', 'Chart of Account', $old, $account->fresh()->toArray(), $account->company_id);

        return redirect()->route('accounts.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Account $account)
    {
        $this->denyReadOnly();
        $this->authorizeCompany($account->company_id);

        if ($account->details()->exists()) {
            return back()->withErrors('Akun yang sudah dipakai transaksi tidak boleh dihapus.');
        }

        $old = $account->toArray();
        $account->delete();
        AuditLog::record('delete', 'Chart of Account', $old, null, $account->company_id);

        return back()->with('success', 'Akun berhasil dihapus.');
    }

    public function export()
    {
        $this->denyViewer();
        AuditLog::record('export', 'Chart of Account');
        $accounts = Account::where('company_id', auth()->user()->company_id)->orderBy('code')->get();
        $rows = ["Kode Akun,Nama Akun,Tipe,Parent,Kategori Pajak,Status"];

        foreach ($accounts as $account) {
            $rows[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', [
                $account->code, $account->name, Account::TYPES[$account->type] ?? $account->type,
                $account->parent?->code, Account::TAX_CATEGORIES[$account->tax_category] ?? 'Bukan Akun Pajak',
                $account->is_active ? 'Aktif' : 'Nonaktif',
            ]));
        }

        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="chart-of-account.csv"',
        ]);
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        $companyId = $account?->company_id ?? auth()->user()->company_id;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('accounts')->where('company_id', $companyId)->ignore($account?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'parent_id' => ['nullable', 'integer'],
            'fiscal_deductibility' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_non_deductible' => ['nullable', 'boolean'],
            'tax_category' => ['nullable', Rule::in(array_keys(Account::TAX_CATEGORIES))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['company_id'] = $companyId;
        if (! empty($data['parent_id'])) {
            $parent = Account::where('company_id', $companyId)->findOrFail($data['parent_id']);
            if ($account && $this->wouldCreateParentCycle($account, $parent)) {
                abort(422, 'Parent akun tidak boleh berupa akun itu sendiri atau turunannya.');
            }

            $data['parent_id'] = $parent->id;
        }
        $data['fiscal_deductibility'] = $request->input('fiscal_deductibility', 100);
        $data['is_non_deductible'] = $request->boolean('is_non_deductible');
        $data['tax_category'] = $request->input('tax_category') ?: null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function accountOptions(?int $exceptId = null)
    {
        return Account::where('company_id', auth()->user()->company_id)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('code')->get();
    }

    private function accountDepths(int $companyId): array
    {
        $accounts = Account::where('company_id', $companyId)->get(['id', 'parent_id'])->keyBy('id');
        $depths = [];

        $depthFor = function (int $id) use (&$depthFor, &$depths, $accounts): int {
            if (array_key_exists($id, $depths)) {
                return $depths[$id];
            }

            $parentId = $accounts[$id]?->parent_id ?? null;
            $depths[$id] = $parentId && $accounts->has($parentId) ? $depthFor((int) $parentId) + 1 : 0;

            return $depths[$id];
        };

        foreach ($accounts->keys() as $id) {
            $depthFor((int) $id);
        }

        return $depths;
    }

    private function wouldCreateParentCycle(Account $account, Account $parent): bool
    {
        $accounts = Account::where('company_id', $account->company_id)->get(['id', 'parent_id'])->keyBy('id');
        $parentId = $parent->id;

        while ($parentId) {
            if ((int) $parentId === (int) $account->id) {
                return true;
            }

            $parentId = $accounts[$parentId]?->parent_id ?? null;
        }

        return false;
    }

    private function authorizeCompany(int $companyId): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $companyId, 403);
    }

    private function denyViewer(): void
    {
        abort_unless(auth()->user()->canManage('account.view'), 403, 'Anda tidak memiliki hak akses daftar akun.');
    }

    private function denyReadOnly(): void
    {
        abort_unless(auth()->user()->canManage('account.manage'), 403, 'Anda tidak memiliki hak akses mengelola akun.');
    }
}
