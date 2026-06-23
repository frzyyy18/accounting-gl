<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\JournalDetail;
use App\Support\CashBankMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        $items = BankReconciliation::with('cashBank', 'branch', 'creator')
            ->where('company_id', auth()->user()->company_id)
            ->latest('statement_date')
            ->paginate(20);

        return view('bank-reconciliations.index', compact('items'));
    }

    public function create(Request $request)
    {
        $this->authorizeCreate();

        $cashBanks = CashBank::with('branch')
            ->where('company_id', auth()->user()->company_id)
            ->where('kind', 'bank')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selected = $request->cash_bank_id
            ? CashBank::where('company_id', auth()->user()->company_id)->find($request->cash_bank_id)
            : null;

        $statementDate = $request->input('statement_date', now()->toDateString());
        $transactions = collect();
        if ($selected) {
            $transactions = $this->unreconciledMovementRows($selected, $statementDate);
        }

        return view('bank-reconciliations.form', compact('cashBanks', 'selected', 'transactions', 'statementDate'));
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();

        $data = $request->validate([
            'cash_bank_id' => ['required', 'integer'],
            'statement_date' => ['required', 'date'],
            'bank_statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
            'movement_keys' => ['nullable', 'array'],
            'movement_keys.*' => ['string'],
            'transaction_ids' => ['nullable', 'array'],
            'transaction_ids.*' => ['integer'],
        ]);

        $cashBank = CashBank::where('company_id', auth()->user()->company_id)->where('kind', 'bank')->findOrFail($data['cash_bank_id']);
        $movementKeys = collect($data['movement_keys'] ?? [])
            ->merge(collect($data['transaction_ids'] ?? [])->map(fn ($id) => 'transaction:'.$id))
            ->unique()
            ->values();
        $movementRows = $this->selectedMovementRows($cashBank, $movementKeys, $data['statement_date']);
        $transactionIds = $movementRows->where('source_type', 'transaction')->pluck('source_id')->unique()->values();
        $journalDetailIds = $movementRows->where('source_type', 'journal_detail')->pluck('source_id')->unique()->values();

        $bookBalance = $this->bookBalance($cashBank, $movementRows);
        $difference = (float) $data['bank_statement_balance'] - $bookBalance;

        $reconciliation = DB::transaction(function () use ($data, $cashBank, $transactionIds, $journalDetailIds, $bookBalance, $difference) {
            $reconciliation = BankReconciliation::create([
                'company_id' => auth()->user()->company_id,
                'branch_id' => $cashBank->branch_id,
                'cash_bank_id' => $cashBank->id,
                'created_by' => auth()->id(),
                'statement_date' => $data['statement_date'],
                'bank_statement_balance' => $data['bank_statement_balance'],
                'book_balance' => $bookBalance,
                'difference' => $difference,
                'status' => round($difference, 2) == 0.0 ? 'reconciled' : 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            CashBankTransaction::whereIn('id', $transactionIds)->update([
                'bank_reconciliation_id' => $reconciliation->id,
                'is_reconciled' => true,
                'reconciled_at' => now(),
            ]);
            JournalDetail::whereIn('id', $journalDetailIds)->update([
                'bank_reconciliation_id' => $reconciliation->id,
                'is_reconciled' => true,
                'reconciled_at' => now(),
            ]);

            AuditLog::record('bank_reconciliation', 'Bank Reconciliation', null, $reconciliation->toArray(), $reconciliation->company_id, $reconciliation->branch_id);

            return $reconciliation;
        });

        return redirect()->route('bank-reconciliations.show', $reconciliation)->with('success', 'Rekonsiliasi bank berhasil disimpan.');
    }

    public function show(BankReconciliation $bankReconciliation)
    {
        $this->authorizeView();
        abort_unless($bankReconciliation->company_id === auth()->user()->company_id || auth()->user()->hasRole('super_admin'), 403);

        return view('bank-reconciliations.show', [
            'item' => $bankReconciliation->load('cashBank', 'branch', 'transactions'),
            'journalDetails' => $bankReconciliation->journalDetails()->with('journalEntry', 'account')->get(),
        ]);
    }

    private function bookBalance(CashBank $cashBank, $movementRows): float
    {
        $balance = (float) $cashBank->opening_balance;
        foreach ($movementRows as $row) {
            $balance += (float) $row['debit'] - (float) $row['credit'];
        }

        return $balance;
    }

    private function unreconciledMovementRows(CashBank $cashBank, ?string $statementDate = null)
    {
        $rows = CashBankMutation::rows($cashBank->company_id, collect([$cashBank]), null, $statementDate);
        $balances = CashBankMutation::openingBalances($cashBank->company_id, collect([$cashBank]));
        $rows = CashBankMutation::withRunningBalances($rows, $balances);
        $transactionIds = $rows->where('source_type', 'transaction')->pluck('source_id')->unique()->values();
        $journalDetailIds = $rows->where('source_type', 'journal_detail')->pluck('source_id')->unique()->values();
        $reconciledTransactions = CashBankTransaction::whereIn('id', $transactionIds)->where('is_reconciled', true)->pluck('id');
        $reconciledDetails = JournalDetail::whereIn('id', $journalDetailIds)->where('is_reconciled', true)->pluck('id');

        return $rows
            ->reject(fn (array $row) => $row['source_type'] === 'transaction' && $reconciledTransactions->contains((int) $row['source_id']))
            ->reject(fn (array $row) => $row['source_type'] === 'journal_detail' && $reconciledDetails->contains((int) $row['source_id']))
            ->values();
    }

    private function selectedMovementRows(CashBank $cashBank, $movementKeys, string $statementDate)
    {
        if ($movementKeys->isEmpty()) {
            return collect();
        }

        $allowedRows = $this->unreconciledMovementRows($cashBank, $statementDate)->keyBy('source_key');

        return $movementKeys
            ->map(fn ($key) => $allowedRows->get($key))
            ->filter()
            ->values();
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()->canManage('bank_reconciliation.view'), 403, 'Anda tidak memiliki hak akses rekonsiliasi bank.');
    }

    private function authorizeCreate(): void
    {
        abort_unless(auth()->user()->canManage('bank_reconciliation.create'), 403, 'Anda tidak memiliki hak akses membuat rekonsiliasi bank.');
    }
}
