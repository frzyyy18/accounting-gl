<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\FiscalPeriod;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Support\CashBankMutation;
use App\Support\TransactionAnomaly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBankTransactionController extends Controller
{
    public function index(Request $request, ?string $kind = null)
    {
        $this->denyViewer();
        $companyId = auth()->user()->company_id;
        $this->ensureCashBankMastersFromPostedJournals($companyId);
        $selectedKind = $kind ?: $request->input('kind');
        if (! in_array($selectedKind, ['cash', 'bank'], true)) {
            $selectedKind = null;
        }

        $cashBanks = CashBank::with('branch', 'account')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($selectedKind, fn ($q) => $q->where('kind', $selectedKind))
            ->orderBy('name')
            ->get();
        $cashBankIds = $cashBanks->pluck('id');
        $selectedCashBankId = $request->integer('cash_bank_id') ?: null;
        if ($selectedCashBankId && ! $cashBankIds->contains($selectedCashBankId)) {
            $selectedCashBankId = null;
        }
        $scopeCashBanks = $selectedCashBankId
            ? $cashBanks->where('id', $selectedCashBankId)->values()
            : $cashBanks;
        $branchId = $request->integer('branch_id') ?: null;
        $sortDirection = $request->input('sort_direction') === 'oldest' ? 'oldest' : 'newest';
        $balances = CashBankMutation::openingBalances($companyId, $scopeCashBanks, $request->input('from'), $branchId);
        $rows = CashBankMutation::rows($companyId, $scopeCashBanks, $request->input('from'), $request->input('to'), $branchId);
        $mutationRows = CashBankMutation::withRunningBalances($rows, $balances);
        if ($sortDirection === 'newest') {
            $mutationRows = $mutationRows->reverse()->values();
        }

        return view('cash-bank-transactions.index', [
            'mutationRows' => $mutationRows,
            'branches' => $this->branches(),
            'cashBanks' => $cashBanks,
            'selectedKind' => $selectedKind,
            'selectedCashBankId' => $selectedCashBankId,
            'sortDirection' => $sortDirection,
        ]);
    }

    public function create(string $type)
    {
        $this->denyReadOnly();
        abort_unless(array_key_exists($type, CashBankTransaction::TYPES), 404);

        return view('cash-bank-transactions.form', $this->formData($type));
    }

    public function store(Request $request, string $type)
    {
        $this->denyReadOnly();
        abort_unless(array_key_exists($type, CashBankTransaction::TYPES), 404);

        $request->merge(['amount' => $this->normalizeMoney($request->input('amount'))]);
        $data = $this->validated($request, $type);
        $this->ensurePeriodCanBeChanged($data['transaction_date']);

        $postingType = $this->postingType($data, $type);

        $transaction = DB::transaction(function () use ($data, $type, $postingType) {
            $journal = $this->createJournal($data, $postingType);

            $transaction = CashBankTransaction::create([
                'company_id' => auth()->user()->company_id,
                'branch_id' => $data['branch_id'],
                'cash_bank_id' => $postingType === 'transfer' && $type === 'bank_in' ? $data['source_cash_bank_id'] : $data['cash_bank_id'],
                'target_cash_bank_id' => $postingType === 'transfer' && $type === 'bank_in' ? $data['cash_bank_id'] : ($data['target_cash_bank_id'] ?? null),
                'counter_account_id' => $postingType === 'transfer' && $type === 'bank_in' ? null : ($data['counter_account_id'] ?? null),
                'journal_entry_id' => $journal->id,
                'transaction_date' => $data['transaction_date'],
                'type' => $postingType,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'attachment_path' => $this->storeAttachment($data['attachment'] ?? null),
                'attachment_name' => isset($data['attachment']) ? $data['attachment']->getClientOriginalName() : null,
                'amount' => $data['amount'],
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            AuditLog::record('create', 'Cash Bank Transaction', null, $transaction->toArray(), $transaction->company_id, $transaction->branch_id);

            return $transaction;
        });

        TransactionAnomaly::recordIfNeeded('Cash Bank Transaction', [
            'cash_bank_transaction_id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'type' => $transaction->type,
        ], (float) $transaction->amount, $transaction->company_id, $transaction->branch_id);

        return redirect()->route('cash-bank-transactions.index')->with('success', CashBankTransaction::TYPES[$type].' berhasil diposting.');
    }

    public function reverse(Request $request, CashBankTransaction $transaction)
    {
        $this->denyReadOnly();
        abort_unless((int) $transaction->company_id === (int) auth()->user()->company_id, 403);
        abort_unless($transaction->status === 'posted', 422, 'Hanya transaksi posted yang dapat dibalik.');
        abort_if($transaction->is_reconciled, 422, 'Transaksi yang sudah direkonsiliasi tidak dapat dibalik.');
        $this->ensurePeriodCanBeChanged($transaction->transaction_date->format('Y-m-d'));

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        DB::transaction(function () use ($transaction, $data) {
            $journal = $transaction->journalEntry?->load('details');
            abort_unless($journal, 422, 'Jurnal transaksi tidak ditemukan.');

            $reversal = JournalEntry::create([
                'company_id' => $transaction->company_id,
                'branch_id' => $transaction->branch_id,
                'transaction_date' => now()->toDateString(),
                'journal_number' => $this->nextJournalNumber(),
                'reference_number' => 'REV-'.($transaction->reference_number ?: $journal->reference_number ?: $journal->journal_number),
                'description' => 'Pembalik: '.($data['reason'] ?: $transaction->description),
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            $reversal->details()->createMany($journal->details->map(fn (JournalDetail $detail) => [
                'account_id' => $detail->account_id,
                'description' => 'Pembalik: '.($detail->description ?: $journal->description),
                'debit' => (float) $detail->credit,
                'credit' => (float) $detail->debit,
                'fiscal_amount' => $detail->fiscal_amount,
                'fiscal_note' => $detail->fiscal_note,
            ])->all());

            $old = $transaction->toArray();
            $transaction->update([
                'status' => 'cancelled',
                'description' => trim(($transaction->description ?: CashBankTransaction::TYPES[$transaction->type]).' | Void: '.$data['reason']),
            ]);

            AuditLog::record('reverse_cash_bank_transaction', 'Cash Bank Transaction', $old, [
                'transaction' => $transaction->fresh()->toArray(),
                'reversal_journal' => $reversal->load('details')->toArray(),
            ], $transaction->company_id, $transaction->branch_id);
        });

        return back()->with('success', 'Transaksi berhasil dibalik dengan jurnal pembalik.');
    }

    private function formData(string $type): array
    {
        $companyId = auth()->user()->company_id;
        $cashBanks = CashBank::with('branch', 'account')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($type === 'cash_in', fn ($q) => $q->where('kind', 'cash'))
            ->when($type === 'bank_in', fn ($q) => $q->where('kind', 'bank'))
            ->orderBy('name')
            ->get();

        return [
            'type' => $type,
            'branches' => $this->branches(),
            'cashBanks' => $cashBanks,
            'counterAccounts' => $this->counterAccounts($companyId, $type),
        ];
    }

    private function validated(Request $request, string $type): array
    {
        $rules = [
            'transaction_date' => ['required', 'date'],
            'branch_id' => ['required', 'integer'],
            'cash_bank_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];

        if ($type === 'transfer') {
            $rules['target_cash_bank_id'] = ['required', 'integer', 'different:cash_bank_id'];
        } else {
            $rules['counter_account_id'] = ['required', 'integer'];
        }

        $data = $request->validate($rules);
        $companyId = auth()->user()->company_id;
        $data['branch_id'] = Branch::where('company_id', $companyId)->where('is_active', true)->findOrFail($data['branch_id'])->id;

        $cashBank = $this->cashBankForBranch($data['cash_bank_id'], $data['branch_id']);
        if ($type === 'cash_in' && ($cashBank->kind ?? 'cash') !== 'cash') {
            throw ValidationException::withMessages([
                'cash_bank_id' => 'Kas masuk wajib masuk ke Kas Kecil, bukan langsung ke bank.',
            ]);
        }
        if ($type === 'bank_in' && ($cashBank->kind ?? 'cash') !== 'bank') {
            throw ValidationException::withMessages([
                'cash_bank_id' => 'Bank masuk wajib memilih rekening bank.',
            ]);
        }

        $data['cash_bank_id'] = $cashBank->id;

        if ($type === 'transfer') {
            $data['target_cash_bank_id'] = $this->cashBankForBranch($data['target_cash_bank_id'], $data['branch_id'])->id;
        } else {
            $counterAccount = $this->counterAccounts($companyId, $type)->firstWhere('id', (int) $data['counter_account_id']);
            if (! $counterAccount) {
                throw ValidationException::withMessages([
                    'counter_account_id' => in_array($type, ['cash_in', 'bank_in'], true)
                        ? 'Kas/Bank masuk hanya boleh memakai akun lawan yang sesuai.'
                        : 'Kas keluar hanya boleh memakai akun lawan Biaya, Hutang, atau Pajak.',
                ]);
            }

            $data['counter_account_id'] = $counterAccount->id;
            if ($type === 'bank_in') {
                $sourceCashBank = CashBank::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('kind', 'cash')
                    ->where('account_id', $counterAccount->id)
                    ->first();

                if ($sourceCashBank) {
                    $data['source_cash_bank_id'] = $this->cashBankForBranch($sourceCashBank->id, $data['branch_id'])->id;
                }
            }
        }

        return $data;
    }

    private function postingType(array $data, string $type): string
    {
        if ($type === 'bank_in' && ! empty($data['source_cash_bank_id'])) {
            return 'transfer';
        }

        return $type;
    }

    private function createJournal(array $data, string $type): JournalEntry
    {
        $targetCashBankId = $data['target_cash_bank_id'] ?? ($type === 'transfer' && isset($data['source_cash_bank_id']) ? $data['cash_bank_id'] : null);
        $sourceCashBankId = $type === 'transfer' && isset($data['source_cash_bank_id']) ? $data['source_cash_bank_id'] : $data['cash_bank_id'];
        $cashBank = CashBank::with('account')->findOrFail($sourceCashBankId);
        $targetCashBank = $targetCashBankId ? CashBank::with('account')->findOrFail($targetCashBankId) : null;
        $counterAccount = isset($data['counter_account_id']) ? Account::findOrFail($data['counter_account_id']) : null;
        $amount = (float) $data['amount'];
        $description = $data['description'] ?: CashBankTransaction::TYPES[$type];

        $journal = JournalEntry::create([
            'company_id' => auth()->user()->company_id,
            'branch_id' => $data['branch_id'],
            'transaction_date' => $data['transaction_date'],
            'journal_number' => $this->nextJournalNumber(),
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $description,
            'status' => 'posted',
            'created_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        if (in_array($type, ['cash_in', 'bank_in'], true)) {
            $journal->details()->createMany([
                ['account_id' => $cashBank->account_id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $counterAccount->id, 'description' => $description, 'debit' => 0, 'credit' => $amount],
            ]);
        }

        if ($type === 'cash_out') {
            $journal->details()->createMany([
                ['account_id' => $counterAccount->id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cashBank->account_id, 'description' => $description, 'debit' => 0, 'credit' => $amount],
            ]);
        }

        if ($type === 'transfer') {
            $journal->details()->createMany([
                ['account_id' => $targetCashBank->account_id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cashBank->account_id, 'description' => $description, 'debit' => 0, 'credit' => $amount],
            ]);
        }

        AuditLog::record('auto_journal', 'Cash Bank Transaction', null, $journal->load('details')->toArray(), $journal->company_id, $data['branch_id']);

        return $journal;
    }

    private function storeAttachment($file): ?string
    {
        return $file ? $file->store('transaction-attachments/cash-bank') : null;
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

    private function nextJournalNumber(): string
    {
        $prefix = 'JV-'.now()->year.'-';
        $last = JournalEntry::where('company_id', auth()->user()->company_id)
            ->where('journal_number', 'like', "$prefix%")
            ->max('journal_number');
        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function ensurePeriodCanBeChanged(string $date): void
    {
        $period = FiscalPeriod::where('company_id', auth()->user()->company_id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($period && ! $period->isEditableBy(auth()->user())) {
            throw ValidationException::withMessages([
                'transaction_date' => 'Periode akuntansi sudah Locked atau Closed. Hubungi admin untuk perubahan.',
            ]);
        }
    }

    private function branches()
    {
        return Branch::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function counterAccounts(int $companyId, string $type)
    {
        $cashBankAccountIds = CashBank::where('company_id', $companyId)
            ->pluck('account_id')
            ->filter()
            ->values();

        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->when($type !== 'bank_in', fn ($q) => $q->whereNotIn('id', $cashBankAccountIds))
            ->when(in_array($type, ['cash_in', 'bank_in'], true), function ($q) {
                $q->where(function ($qq) {
                    $qq->whereIn('type', ['revenue', 'other_income'])
                        ->orWhere('name', 'like', '%Piutang%')
                        ->orWhere('code', 'like', '120.%')
                        ->orWhere('code', 'like', '100.%');
                });
            })
            ->when($type === 'cash_out', function ($q) {
                $q->where(function ($qq) {
                    $qq->whereIn('type', ['expense', 'other_expense', 'liability'])
                        ->orWhere('name', 'like', '%Biaya Dibayar Dimuka%')
                        ->orWhere('name', 'like', '%PPN Masukan%')
                        ->orWhere('name', 'like', '%PPh%');
                });
            })
            ->orderBy('code')
            ->get();
    }

    private function cashBankForBranch(int $cashBankId, int $branchId): CashBank
    {
        $cashBank = CashBank::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->findOrFail($cashBankId);

        if (($cashBank->scope ?? 'company') === 'branch' && (int) $cashBank->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'cash_bank_id' => 'Bank cabang hanya boleh dipakai pada cabang yang sesuai.',
            ]);
        }

        return $cashBank;
    }

    private function ensureCashBankMastersFromPostedJournals(int $companyId): void
    {
        $details = JournalDetail::with('account', 'journalEntry')
            ->whereHas('journalEntry', fn ($q) => $q->where('company_id', $companyId)->where('status', 'posted'))
            ->whereHas('account', fn ($q) => $q->where('company_id', $companyId)->where('type', 'asset'))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cash_banks')
                    ->whereColumn('cash_banks.account_id', 'journal_details.account_id')
                    ->whereNull('cash_banks.deleted_at');
            })
            ->get()
            ->unique('account_id');

        foreach ($details as $detail) {
            $account = $detail->account;
            $kind = $this->cashBankKindForAccount($account);
            if (! $kind) {
                continue;
            }

            CashBank::firstOrCreate([
                'company_id' => $companyId,
                'account_id' => $account->id,
            ], [
                'company_id' => $companyId,
                'branch_id' => $kind === 'bank' ? $detail->journalEntry?->branch_id : null,
                'scope' => $kind === 'bank' ? 'branch' : 'company',
                'kind' => $kind,
                'account_id' => $account->id,
                'name' => $account->name,
                'bank_name' => $kind === 'bank' ? $account->name : null,
                'opening_balance' => 0,
                'is_active' => true,
            ]);
        }
    }

    private function cashBankKindForAccount(Account $account): ?string
    {
        $code = (string) $account->code;
        $name = strtoupper((string) $account->name);

        if (str_starts_with($code, '110.') || preg_match('/\bBANK\b/', $name)) {
            return 'bank';
        }

        if (str_starts_with($code, '100.') || preg_match('/\b(KAS|CASH|KOIN)\b/', $name)) {
            return 'cash';
        }

        return null;
    }

    private function denyViewer(): void
    {
        abort_unless(auth()->user()->canManage('cash_transaction.view'), 403, 'Anda tidak memiliki hak akses mutasi kas/bank.');
    }

    private function denyReadOnly(): void
    {
        abort_unless(auth()->user()->canManage('cash_transaction.create'), 403, 'Anda tidak memiliki hak akses input kas/bank.');
    }

}
