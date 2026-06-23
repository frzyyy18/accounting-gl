<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Support\TransactionAnomaly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableReceiptController extends Controller
{
    public function create()
    {
        $companyId = auth()->user()->company_id;

        return view('receivable-receipts.create', [
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'debitAccounts' => $this->debitAccounts($companyId),
            'defaultDebitAccountId' => Account::where('company_id', $companyId)->where('code', '100.04')->value('id'),
            'receivableAccounts' => $this->receivableAccounts($companyId),
            'defaultReceivableAccountId' => Account::where('company_id', $companyId)->where('code', '120.01')->value('id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['amount' => $this->normalizeMoney($request->input('amount'))]);
        $details = collect($request->input('details', []))
            ->map(function ($item) {
                $item['amount'] = $this->normalizeMoney($item['amount'] ?? null);

                return $item;
            })
            ->filter(fn ($item) => filled($item['account_id'] ?? null) || (float) ($item['amount'] ?? 0) > 0)
            ->values()
            ->all();
        $request->merge(['details' => $details]);

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'branch_id' => ['required', 'integer'],
            'voucher_code' => ['required', 'string', 'max:100'],
            'receivable_account_id' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.account_id' => ['required', 'integer'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensurePeriodCanBeChanged($data['transaction_date']);
        $branchId = Branch::where('company_id', $companyId)->where('is_active', true)->findOrFail($data['branch_id'])->id;
        $details = $this->validatedDetails($companyId, $data);
        $receivable = $this->receivableAccounts($companyId)->firstWhere('id', (int) $data['receivable_account_id']);
        if (! $receivable) {
            throw ValidationException::withMessages([
                'receivable_account_id' => 'Akun Piutang Dagang tidak valid.',
            ]);
        }

        $transaction = DB::transaction(function () use ($data, $companyId, $branchId, $details, $receivable) {
            $totalAmount = array_sum(array_column($details, 'amount'));
            $voucherCode = strtoupper(trim($data['voucher_code']));
            $description = ($data['description'] ?? null) ?: 'Terima voucher kasir '.$voucherCode;

            $journal = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transaction_date' => $data['transaction_date'],
                'journal_number' => $this->nextJournalNumber($companyId),
                'reference_number' => $voucherCode,
                'description' => $description,
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            $journalDetails = [];
            foreach ($details as $item) {
                $lineDescription = $item['description'] ?: $description;
                $journalDetails[] = ['account_id' => $item['account']->id, 'description' => $lineDescription, 'debit' => $item['amount'], 'credit' => 0];
            }
            $journalDetails[] = ['account_id' => $receivable->id, 'description' => $description, 'debit' => 0, 'credit' => $totalAmount];

            $journal->details()->createMany($journalDetails);
            $cashBankMovements = $this->cashBankMovements($companyId, $details);
            if ($cashBankMovements->count() !== 1) {
                throw ValidationException::withMessages([
                    'details' => 'Voucher kasir harus memiliki tepat satu baris debit akun kas/bank aktif.',
                ]);
            }
            $cashBankMovement = $cashBankMovements->first();
            $cashBankAmount = (float) $cashBankMovement['amount'];
            if (round($cashBankAmount, 2) !== round($totalAmount, 2)) {
                throw ValidationException::withMessages([
                    'details' => 'Total voucher harus masuk ke satu akun kas/bank agar mutasi kas sesuai jurnal.',
                ]);
            }

            $transaction = CashBankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'cash_bank_id' => $cashBankMovement['cash_bank']->id,
                'counter_account_id' => $receivable->id,
                'journal_entry_id' => $journal->id,
                'transaction_date' => $data['transaction_date'],
                'type' => 'cash_in',
                'reference_number' => $voucherCode,
                'description' => $description,
                'amount' => $cashBankAmount,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            AuditLog::record('create_receivable_receipt', 'Terima Piutang', null, [
                'transaction' => $transaction->toArray(),
                'journal' => $journal->load('details')->toArray(),
            ], $companyId, $branchId);

            return $transaction;
        });

        TransactionAnomaly::recordIfNeeded('Terima Piutang', [
            'cash_bank_transaction_id' => $transaction->id,
            'voucher_code' => $transaction->reference_number,
        ], (float) $transaction->amount, $transaction->company_id, $transaction->branch_id);

        return redirect()->route('cash-bank-transactions.index')->with('success', 'Voucher kasir berhasil diposting ke Kas Tagihan.');
    }

    private function validatedDetails(int $companyId, array $data): array
    {
        $accounts = $this->debitAccounts($companyId)->keyBy('id');

        return collect($data['details'])->map(function ($item) use ($accounts) {
            $account = $accounts->get((int) $item['account_id']);
            if (! $account) {
                throw ValidationException::withMessages([
                    'details' => 'Akun debit tidak valid untuk voucher kasir.',
                ]);
            }

            return [
                'account' => $account,
                'description' => $item['description'] ?? null,
                'amount' => (float) $item['amount'],
            ];
        })->all();
    }

    private function debitAccounts(int $companyId)
    {
        $cashBankAccountIds = CashBank::where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('account_id')
            ->filter()
            ->values();

        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $cashBankAccountIds)
            ->orderBy('code')
            ->get();
    }

    private function receivableAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('code', 'like', '120.%')->orWhere('name', 'like', '%Piutang Dagang%'))
            ->orderBy('code')
            ->get();
    }

    private function cashBankMovements(int $companyId, array $details)
    {
        $cashBankByAccount = CashBank::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->keyBy('account_id');

        return collect($details)
            ->filter(fn ($item) => $cashBankByAccount->has((int) $item['account']->id))
            ->groupBy(fn ($item) => (int) $item['account']->id)
            ->map(fn ($items, $accountId) => [
                'cash_bank' => $cashBankByAccount->get((int) $accountId),
                'amount' => (float) $items->sum('amount'),
            ])
            ->values();
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

    private function nextJournalNumber(int $companyId): string
    {
        $prefix = 'JV-'.now()->year.'-';
        $last = JournalEntry::where('company_id', $companyId)->where('journal_number', 'like', "$prefix%")->max('journal_number');
        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
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
}
