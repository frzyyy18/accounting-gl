<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ApprovalLog;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Support\TransactionAnomaly;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', JournalEntry::class);
        $companyId = $this->selectedCompanyIdForIndex($request);
        $sorts = [
            'transaction_date' => 'transaction_date',
            'branch' => 'branch_id',
            'journal_number' => 'journal_number',
            'reference_number' => 'reference_number',
            'description' => 'description',
            'status' => 'status',
        ];
        $sort = $sorts[$request->input('sort')] ?? 'transaction_date';
        $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $journals = JournalEntry::with('creator', 'company')
            ->withSum('details as total_amount', 'debit')
            ->with('branch')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q) => $q->whereDate('transaction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('transaction_date', '<=', $request->to))
            ->when($request->q, function ($q) use ($request) {
                $search = '%'.$request->q.'%';
                $q->where(fn ($query) => $query
                    ->where('journal_number', 'like', $search)
                    ->orWhere('reference_number', 'like', $search)
                    ->orWhere('description', 'like', $search));
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(15)
            ->withQueryString();

        return view('journals.index', [
            'journals' => $journals,
            'branches' => $this->branches($companyId),
            'companies' => $this->companies(),
            'selectedCompanyId' => $companyId,
        ]);
    }

    public function create()
    {
        $this->authorize('create', JournalEntry::class);
        $companyId = $this->selectedCompanyId(request());

        return view('journals.form', [
            'journal' => new JournalEntry(['company_id' => $companyId, 'transaction_date' => now(), 'status' => 'draft']),
            'accounts' => $this->accounts($companyId),
            'branches' => $this->branches($companyId),
            'cashBanks' => $this->cashBanks($companyId),
            'companies' => $this->companies(),
            'selectedCompanyId' => $companyId,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', JournalEntry::class);
        $this->normalizeMoneyInputs($request);
        $data = $this->validated($request);
        $this->ensurePeriodCanBeChanged($data['transaction_date'], $data['company_id']);
        $this->ensureStatusAllowed($data['status']);

        $journal = DB::transaction(function () use ($data) {
            $journal = JournalEntry::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'transaction_date' => $data['transaction_date'],
                'journal_number' => $data['journal_number'] ?: $this->nextNumber($data['company_id']),
                'reference_number' => ($data['reference_number'] ?? null) ?: $this->nextManualVoucherNumber($data['company_id'], (int) $data['branch_id'], $data['transaction_date']),
                'description' => $data['description'] ?? null,
                'attachment_path' => $this->storeAttachment($data['attachment'] ?? null),
                'attachment_name' => isset($data['attachment']) ? $data['attachment']->getClientOriginalName() : null,
                'status' => $data['status'],
                'created_by' => auth()->id(),
                'submitted_by' => $data['status'] === 'submitted' ? auth()->id() : null,
                'posted_at' => null,
            ]);

            $journal->details()->createMany($data['details']);
            AuditLog::record('create', 'General Journal', null, $journal->load('details')->toArray(), $journal->company_id, $journal->branch_id);

            return $journal;
        });

        TransactionAnomaly::recordIfNeeded('General Journal', [
            'journal_entry_id' => $journal->id,
            'journal_number' => $journal->journal_number,
        ], (float) $journal->details()->sum('debit'), $journal->company_id, $journal->branch_id);

        return redirect()->route('journals.show', $journal)->with('success', 'Jurnal berhasil disimpan.');
    }

    public function show(JournalEntry $journal)
    {
        $this->authorize('view', $journal);
        $journal->load('details.account', 'creator', 'branch');

        return view('journals.show', [
            'journal' => $journal,
            'previousJournal' => $this->adjacentJournal($journal, 'previous'),
            'nextJournal' => $this->adjacentJournal($journal, 'next'),
            'accountSummary' => $journal->details
                ->groupBy('account_id')
                ->map(function ($details) {
                    $first = $details->first();

                    return (object) [
                        'account' => $first->account,
                        'debit' => (float) $details->sum('debit'),
                        'credit' => (float) $details->sum('credit'),
                        'activity' => (float) $details->sum('debit') + (float) $details->sum('credit'),
                    ];
                })
                ->sortByDesc('activity')
                ->values(),
        ]);
    }

    public function edit(JournalEntry $journal)
    {
        $this->authorize('update', $journal);
        abort_if(in_array($journal->status, ['posted', 'cancelled'], true), 403, 'Jurnal posted/cancelled tidak boleh diedit.');
        $this->ensurePeriodCanBeChanged($journal->transaction_date->format('Y-m-d'), $journal->company_id);

        return view('journals.form', [
            'journal' => $journal->load('details'),
            'accounts' => $this->accounts($journal->company_id),
            'branches' => $this->branches($journal->company_id),
            'cashBanks' => $this->cashBanks($journal->company_id),
            'companies' => $this->companies(),
            'selectedCompanyId' => $journal->company_id,
        ]);
    }

    public function duplicate(JournalEntry $journal)
    {
        $this->authorize('view', $journal);
        $this->authorize('create', JournalEntry::class);

        $companyId = $journal->company_id;
        $copy = new JournalEntry([
            'company_id' => $companyId,
            'branch_id' => $journal->branch_id,
            'transaction_date' => now(),
            'status' => 'draft',
            'description' => $journal->description,
        ]);
        $copy->setRelation('details', $journal->details->map(function ($detail) {
            return $detail->replicate(['journal_entry_id']);
        }));

        return view('journals.form', [
            'journal' => $copy,
            'accounts' => $this->accounts($companyId),
            'branches' => $this->branches($companyId),
            'cashBanks' => $this->cashBanks($companyId),
            'companies' => $this->companies(),
            'selectedCompanyId' => $companyId,
            'duplicatedFrom' => $journal,
        ]);
    }

    public function update(Request $request, JournalEntry $journal)
    {
        $this->authorize('update', $journal);
        abort_if(in_array($journal->status, ['posted', 'cancelled'], true), 403, 'Jurnal posted/cancelled tidak boleh diedit.');
        $this->normalizeMoneyInputs($request);
        $data = $this->validated($request, $journal);
        $this->ensurePeriodCanBeChanged($journal->transaction_date->format('Y-m-d'), $journal->company_id);
        $this->ensurePeriodCanBeChanged($data['transaction_date'], $journal->company_id);
        $this->ensureStatusAllowed($data['status']);
        $old = $journal->load('details')->toArray();

        DB::transaction(function () use ($journal, $data, $old) {
            $attachmentPath = $journal->attachment_path;
            $attachmentName = $journal->attachment_name;
            if (isset($data['attachment'])) {
                if ($attachmentPath) {
                    Storage::disk('local')->delete($attachmentPath);
                }
                $attachmentPath = $this->storeAttachment($data['attachment']);
                $attachmentName = $data['attachment']->getClientOriginalName();
            }

            $journal->update([
                'transaction_date' => $data['transaction_date'],
                'branch_id' => $data['branch_id'],
                'journal_number' => $data['journal_number'] ?: $journal->journal_number,
                'reference_number' => ($data['reference_number'] ?? null) ?: ($journal->reference_number ?: $this->nextManualVoucherNumber($journal->company_id, (int) $data['branch_id'], $data['transaction_date'])),
                'description' => $data['description'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => $data['status'],
                'submitted_by' => $data['status'] === 'submitted' ? auth()->id() : null,
                'posted_at' => null,
            ]);
            $journal->details()->delete();
            $journal->details()->createMany($data['details']);
            AuditLog::record('update', 'General Journal', $old, $journal->fresh('details')->toArray(), $journal->company_id, $journal->branch_id);
        });

        return redirect()->route('journals.show', $journal)->with('success', 'Jurnal berhasil diperbarui.');
    }

    public function destroy(JournalEntry $journal)
    {
        $this->authorize('delete', $journal);
        abort_if(in_array($journal->status, ['posted', 'cancelled'], true), 403, 'Jurnal posted/cancelled tidak boleh dihapus.');
        $this->ensurePeriodCanBeChanged($journal->transaction_date->format('Y-m-d'), $journal->company_id);
        $old = $journal->load('details')->toArray();
        $journal->delete();
        AuditLog::record('delete', 'General Journal', $old, null, $journal->company_id, $journal->branch_id);

        return redirect()->route('journals.index')->with('success', 'Jurnal berhasil dihapus.');
    }

    public function submit(JournalEntry $journal)
    {
        $this->authorize('submit', $journal);
        abort_unless($journal->status === 'draft', 422, 'Hanya jurnal Draft yang dapat disubmit.');
        $this->ensurePeriodCanBeChanged($journal->transaction_date->format('Y-m-d'), $journal->company_id);

        return $this->changeWorkflowStatus($journal, 'submitted', 'submit_journal', 'Jurnal berhasil disubmit.');
    }

    public function approve(JournalEntry $journal, Request $request)
    {
        $this->authorize('approve', $journal);
        abort_unless($journal->status === 'submitted', 422, 'Hanya jurnal Submitted yang dapat diapprove.');

        $submitterId = $journal->submitted_by ?: $journal->created_by;
        if ((int) $submitterId === (int) auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menyetujui jurnal yang Anda ajukan sendiri.');
        }

        return $this->changeWorkflowStatus($journal, 'approved', 'approve_journal', 'Jurnal berhasil diapprove.', $request->input('notes'), true);
    }

    public function reject(JournalEntry $journal, Request $request)
    {
        $this->authorize('reject', $journal);
        abort_unless($journal->status === 'submitted', 422, 'Hanya jurnal Submitted yang dapat direject.');

        return $this->changeWorkflowStatus($journal, 'rejected', 'reject_journal', 'Jurnal berhasil direject.', $request->input('notes'), true);
    }

    public function cancel(JournalEntry $journal, Request $request)
    {
        abort_unless(in_array($journal->status, ['draft', 'submitted', 'rejected'], true), 422, 'Hanya jurnal Draft, Submitted, atau Rejected yang dapat dibatalkan.');
        abort_unless((int) $journal->created_by === (int) auth()->id() || auth()->user()->hasRole('super_admin'), 403, 'Anda tidak berwenang membatalkan jurnal ini.');

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:10'],
        ]);

        $old = $journal->toArray();
        $journal->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
            'cancellation_reason' => $data['cancellation_reason'],
        ]);

        AuditLog::record('cancel_journal', 'General Journal', $old, $journal->fresh()->toArray(), $journal->company_id, $journal->branch_id);

        return redirect()->route('journals.index')->with('success', 'Jurnal berhasil dibatalkan.');
    }

    public function post(JournalEntry $journal)
    {
        $this->authorize('post', $journal);
        abort_unless($journal->status === 'approved', 422, 'Hanya jurnal Approved yang dapat diposting.');
        $this->ensurePeriodCanBeChanged($journal->transaction_date->format('Y-m-d'), $journal->company_id);

        $old = $journal->toArray();
        $journal->update(['status' => 'posted', 'posted_at' => now()]);
        ApprovalLog::create(['journal_entry_id' => $journal->id, 'user_id' => auth()->id(), 'action' => 'posted']);
        AuditLog::record('post_journal', 'General Journal', $old, $journal->fresh()->toArray(), $journal->company_id, $journal->branch_id);

        return redirect()->route('journals.show', $journal)->with('success', 'Jurnal berhasil diposting dan masuk laporan.');
    }

    public function voucherNumber(Request $request)
    {
        $this->authorize('create', JournalEntry::class);

        $data = $request->validate([
            'cash_bank_id' => ['required', 'integer'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
        ]);
        $companyId = $this->validatedCompanyId($request);

        $cashBank = $this->cashBankForBranch((int) $data['cash_bank_id'], isset($data['branch_id']) ? (int) $data['branch_id'] : null, $companyId);

        return response()->json([
            'reference_number' => $this->nextVoucherNumber(
                $cashBank,
                $data['transaction_date'] ?? now()->toDateString(),
                isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                $companyId
            ),
        ]);
    }

    private function validated(Request $request, ?JournalEntry $journal = null): array
    {
        $companyId = $journal?->company_id ?? $this->validatedCompanyId($request);
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'transaction_date' => ['required', 'date'],
            'branch_id' => ['required', 'integer'],
            'journal_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('journal_entries')->where('company_id', $companyId)->ignore($journal?->id),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'voucher_cash_bank_id' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'status' => ['required', 'in:draft,submitted'],
            'details' => ['required', 'array', 'min:2'],
            'details.*.account_id' => ['required', 'integer'],
            'details.*.description' => ['nullable', 'string', 'max:255'],
            'details.*.debit' => ['nullable', 'numeric', 'min:0'],
            'details.*.credit' => ['nullable', 'numeric', 'min:0'],
            'details.*.fiscal_amount' => ['nullable', 'numeric', 'min:0'],
            'details.*.fiscal_note' => ['nullable', 'string', 'max:255'],
        ]);

        $data['branch_id'] = Branch::where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($data['branch_id'])->id;

        $data['journal_number'] = ($data['journal_number'] ?? null) ? strtoupper(trim($data['journal_number'])) : null;
        $data['reference_number'] = ($data['reference_number'] ?? null) ? strtoupper(trim($data['reference_number'])) : null;

        if (! empty($data['voucher_cash_bank_id'])) {
            $data['voucher_cash_bank_id'] = $this->cashBankForBranch((int) $data['voucher_cash_bank_id'], (int) $data['branch_id'], $companyId)->id;
        }

        $details = [];
        foreach ($data['details'] as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $lineAmount = max($debit, $credit);

            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw ValidationException::withMessages(['details' => 'Satu baris wajib berisi debit atau kredit saja.']);
            }

            if (isset($line['fiscal_amount']) && $line['fiscal_amount'] !== '' && (float) $line['fiscal_amount'] > $lineAmount) {
                throw ValidationException::withMessages(['details' => 'Nilai fiskal tidak boleh melebihi nominal debit/kredit pada baris yang sama.']);
            }

            $account = Account::where('company_id', $companyId)->where('is_active', true)->find($line['account_id']);
            if (! $account) {
                throw ValidationException::withMessages(['details' => 'Akun tidak aktif atau tidak ditemukan.']);
            }

            $details[] = [
                'account_id' => $account->id,
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'fiscal_amount' => isset($line['fiscal_amount']) && $line['fiscal_amount'] !== '' ? (float) $line['fiscal_amount'] : null,
                'fiscal_note' => $line['fiscal_note'] ?? null,
            ];
        }

        $totalDebit = round(array_sum(array_column($details, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($details, 'credit')), 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages(['details' => 'Total debit harus sama dengan total kredit.']);
        }

        $data['details'] = $details;
        $data['company_id'] = $companyId;

        return $data;
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'JV-'.now()->year.'-';
        $last = JournalEntry::where('company_id', $companyId)->where('journal_number', 'like', "$prefix%")->max('journal_number');
        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function voucherReferenceFromData(array $data): ?string
    {
        if (empty($data['voucher_cash_bank_id'])) {
            return null;
        }

        $cashBank = CashBank::with('account')->where('company_id', $data['company_id'])->findOrFail($data['voucher_cash_bank_id']);

        return $this->nextVoucherNumber($cashBank, $data['transaction_date'], (int) $data['branch_id'], $data['company_id']);
    }

    private function nextManualVoucherNumber(int $companyId, int $branchId, string $date): string
    {
        $period = Carbon::parse($date)->format('Ym');
        $companyCode = $this->voucherCompanyCode($companyId);
        $branchCode = $this->voucherCode(Branch::where('company_id', $companyId)->find($branchId)?->code ?: 'PUSAT', 20);
        $prefix = "VCH-{$companyCode}-{$branchCode}-{$period}-";
        $last = JournalEntry::where('company_id', $companyId)
            ->where('reference_number', 'like', "$prefix%")
            ->max('reference_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextVoucherNumber(CashBank $cashBank, string $date, ?int $branchId = null, ?int $companyId = null): string
    {
        $companyId ??= $cashBank->company_id;
        $period = Carbon::parse($date)->format('Ym');
        $companyCode = $this->voucherCompanyCode($companyId);
        $branchCode = $this->voucherBranchCode($cashBank, $branchId, $companyId);
        $bankCode = $this->voucherBankCode($cashBank);
        $prefix = "VCH-{$companyCode}-{$branchCode}-{$bankCode}-{$period}-";
        $last = JournalEntry::where('company_id', $companyId)
            ->where('reference_number', 'like', "$prefix%")
            ->max('reference_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function voucherCompanyCode(int $companyId): string
    {
        $company = Company::find($companyId);

        return $this->voucherCode($company?->code ?: $company?->name ?: 'COMP'.$companyId, 20);
    }

    private function voucherBranchCode(CashBank $cashBank, ?int $branchId, int $companyId): string
    {
        $branch = $branchId ? Branch::where('company_id', $companyId)->find($branchId) : null;
        $branch ??= $cashBank->branch;

        return $this->voucherCode($branch?->code ?: 'PUSAT', 20);
    }

    private function voucherBankCode(CashBank $cashBank): string
    {
        $source = $cashBank->account?->code ?: $cashBank->name;

        return $this->voucherCode($source ?: 'BANK', 12);
    }

    private function voucherCode(string $value, int $length): string
    {
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));

        return substr($code ?: 'NA', 0, $length);
    }

    private function branches(?int $companyId)
    {
        return Branch::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function accounts(int $companyId)
    {
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function cashBanks(int $companyId)
    {
        return CashBank::with('branch', 'account')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function cashBankForBranch(int $cashBankId, ?int $branchId, int $companyId): CashBank
    {
        $cashBank = CashBank::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('branch', 'account')
            ->findOrFail($cashBankId);

        if ($branchId && ($cashBank->scope ?? 'company') === 'branch' && (int) $cashBank->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'voucher_cash_bank_id' => 'Bank cabang hanya boleh dipakai pada cabang yang sesuai.',
            ]);
        }

        return $cashBank;
    }

    private function selectedCompanyId(Request $request): int
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return (int) (auth()->user()->company_id ?: Company::orderBy('name')->value('id'));
        }

        $requested = $request->integer('company_id');
        if ($requested && Company::whereKey($requested)->exists()) {
            return $requested;
        }

        return (int) (auth()->user()->company_id ?: Company::orderBy('name')->value('id'));
    }

    private function selectedCompanyIdForIndex(Request $request): ?int
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return (int) (auth()->user()->company_id ?: Company::orderBy('name')->value('id'));
        }

        $requested = $request->integer('company_id');
        if ($requested && Company::whereKey($requested)->exists()) {
            return $requested;
        }

        return null;
    }

    private function validatedCompanyId(Request $request): int
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return (int) (auth()->user()->company_id ?: Company::orderBy('name')->value('id'));
        }

        $companyId = $request->integer('company_id');
        if ($companyId && Company::whereKey($companyId)->exists()) {
            return $companyId;
        }

        return (int) (auth()->user()->company_id ?: Company::orderBy('name')->value('id'));
    }

    private function companies()
    {
        return Company::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }

    private function adjacentJournal(JournalEntry $journal, string $direction): ?JournalEntry
    {
        $query = JournalEntry::query()
            ->where('company_id', $journal->company_id)
            ->where(fn ($q) => $direction === 'previous'
                ? $q->whereDate('transaction_date', '<', $journal->transaction_date)
                    ->orWhere(fn ($qq) => $qq->whereDate('transaction_date', $journal->transaction_date)->where('id', '<', $journal->id))
                : $q->whereDate('transaction_date', '>', $journal->transaction_date)
                    ->orWhere(fn ($qq) => $qq->whereDate('transaction_date', $journal->transaction_date)->where('id', '>', $journal->id)));

        return $query
            ->orderBy('transaction_date', $direction === 'previous' ? 'desc' : 'asc')
            ->orderBy('id', $direction === 'previous' ? 'desc' : 'asc')
            ->first();
    }

    private function ensurePeriodCanBeChanged(string $date, ?int $companyId = null): void
    {
        $companyId ??= auth()->user()->company_id;
        $period = FiscalPeriod::where('company_id', $companyId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($period && ! $period->isEditableBy(auth()->user())) {
            throw ValidationException::withMessages([
                'transaction_date' => 'Periode akuntansi sudah Locked atau Closed. Hubungi admin untuk perubahan.',
            ]);
        }
    }

    private function ensureStatusAllowed(string $status): void
    {
        if ($status === 'submitted') {
            return;
        }

        if ($status === 'draft') {
            return;
        }

        throw ValidationException::withMessages(['status' => 'Status jurnal tidak valid untuk input manual.']);
    }

    private function storeAttachment($file): ?string
    {
        return $file ? $file->store('transaction-attachments/journals') : null;
    }

    private function normalizeMoneyInputs(Request $request): void
    {
        $details = collect($request->input('details', []))
            ->map(function ($line) {
                $line['debit'] = $this->normalizeMoney($line['debit'] ?? 0);
                $line['credit'] = $this->normalizeMoney($line['credit'] ?? 0);
                if (array_key_exists('fiscal_amount', $line)) {
                    $line['fiscal_amount'] = trim((string) ($line['fiscal_amount'] ?? '')) === ''
                        ? ''
                        : $this->normalizeMoney($line['fiscal_amount']);
                }

                return $line;
            })
            ->all();

        $request->merge(['details' => $details]);
    }

    private function normalizeMoney(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '0';
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (str_contains($value, ',')) {
            return str_replace(',', '.', $value);
        }

        return $value;
    }

    private function changeWorkflowStatus(JournalEntry $journal, string $status, string $action, string $message, ?string $notes = null, bool $redirectToShow = false)
    {
        $old = $journal->toArray();
        $journal->update([
            'status' => $status,
            'submitted_by' => $status === 'submitted' ? auth()->id() : $journal->submitted_by,
            'approved_by' => in_array($status, ['approved', 'rejected'], true) ? auth()->id() : $journal->approved_by,
        ]);
        ApprovalLog::create([
            'journal_entry_id' => $journal->id,
            'user_id' => auth()->id(),
            'action' => $status,
            'notes' => $notes,
        ]);
        AuditLog::record($action, 'General Journal', $old, $journal->fresh()->toArray(), $journal->company_id, $journal->branch_id);

        if ($redirectToShow) {
            return redirect()->route('journals.show', $journal)->with('success', $message);
        }

        return back()->with('success', $message);
    }
}
