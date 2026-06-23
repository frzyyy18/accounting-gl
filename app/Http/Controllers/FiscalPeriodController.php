<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FiscalPeriodController extends Controller
{
    public function index()
    {
        $periods = FiscalPeriod::where('company_id', auth()->user()->company_id)
            ->orderByDesc('start_date')
            ->paginate(15);

        return view('fiscal-periods.index', compact('periods'));
    }

    public function create()
    {
        return view('fiscal-periods.form', ['period' => new FiscalPeriod(['status' => 'open'])]);
    }

    public function store(Request $request)
    {
        $period = FiscalPeriod::create($this->validated($request));
        AuditLog::record('create', 'Fiscal Period', null, $period->toArray(), $period->company_id);

        return redirect()->route('fiscal-periods.index')->with('success', 'Periode akuntansi berhasil dibuat.');
    }

    public function show(FiscalPeriod $fiscalPeriod)
    {
        return redirect()->route('fiscal-periods.edit', $fiscalPeriod);
    }

    public function edit(FiscalPeriod $fiscalPeriod)
    {
        $this->authorizeCompany($fiscalPeriod->company_id);

        return view('fiscal-periods.form', ['period' => $fiscalPeriod]);
    }

    public function update(Request $request, FiscalPeriod $fiscalPeriod)
    {
        $this->authorizeCompany($fiscalPeriod->company_id);
        $old = $fiscalPeriod->toArray();
        $fiscalPeriod->update($this->validated($request, $fiscalPeriod));
        AuditLog::record('update', 'Fiscal Period', $old, $fiscalPeriod->fresh()->toArray(), $fiscalPeriod->company_id);

        return redirect()->route('fiscal-periods.index')->with('success', 'Periode akuntansi berhasil diperbarui.');
    }

    public function destroy(FiscalPeriod $fiscalPeriod)
    {
        $this->authorizeCompany($fiscalPeriod->company_id);
        abort_unless(auth()->user()->hasRole(['super_admin', 'manager_internal', 'omm']), 403);
        if (JournalEntry::where('company_id', $fiscalPeriod->company_id)
            ->whereBetween('transaction_date', [$fiscalPeriod->start_date, $fiscalPeriod->end_date])
            ->exists()) {
            return back()->withErrors('Periode yang sudah memiliki transaksi/jurnal tidak boleh dihapus.');
        }

        $old = $fiscalPeriod->toArray();
        $fiscalPeriod->delete();
        AuditLog::record('delete', 'Fiscal Period', $old, null, $fiscalPeriod->company_id);

        return back()->with('success', 'Periode akuntansi berhasil dihapus.');
    }

    public function lock(FiscalPeriod $fiscalPeriod)
    {
        return $this->changeStatus($fiscalPeriod, 'locked', 'lock_period');
    }

    public function unlock(FiscalPeriod $fiscalPeriod)
    {
        return $this->changeStatus($fiscalPeriod, 'open', 'unlock_period');
    }

    public function close(FiscalPeriod $fiscalPeriod)
    {
        return $this->changeStatus($fiscalPeriod, 'closed', 'close_period');
    }

    private function changeStatus(FiscalPeriod $period, string $status, string $action)
    {
        $this->authorizeCompany($period->company_id);
        abort_unless(auth()->user()->hasRole(['super_admin', 'manager_internal', 'omm']), 403);
        $old = $period->toArray();
        $period->update(['status' => $status]);
        AuditLog::record($action, 'Fiscal Period', $old, $period->fresh()->toArray(), $period->company_id);

        return back()->with('success', 'Status periode berhasil diperbarui.');
    }

    private function validated(Request $request, ?FiscalPeriod $fiscalPeriod = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
        ]);

        $data['company_id'] = $fiscalPeriod?->company_id ?? auth()->user()->company_id;

        $overlapExists = FiscalPeriod::where('company_id', $data['company_id'])
            ->when($fiscalPeriod, fn ($q) => $q->whereKeyNot($fiscalPeriod->id))
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlapExists) {
            abort(422, 'Periode akuntansi tidak boleh tumpang tindih dengan periode lain.');
        }

        return $data;
    }

    private function authorizeCompany(int $companyId): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $companyId, 403);
    }
}
