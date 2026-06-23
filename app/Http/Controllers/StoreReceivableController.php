<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Support\TransactionAnomaly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreReceivableController extends Controller
{
    public function create()
    {
        $companyId = auth()->user()->company_id;

        return view('store-receivables.create', [
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'receivableAccounts' => $this->accounts($companyId, 'receivable'),
            'salesAccounts' => $this->accounts($companyId, 'sales'),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['amount' => $this->normalizeMoney($request->input('amount'))]);
        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'branch_id' => ['required', 'integer'],
            'receivable_account_id' => ['required', 'integer'],
            'sales_account_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'store_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensurePeriodCanBeChanged($data['transaction_date']);
        $branchId = Branch::where('company_id', $companyId)->where('is_active', true)->findOrFail($data['branch_id'])->id;
        $receivable = $this->accounts($companyId, 'receivable')->firstWhere('id', (int) $data['receivable_account_id']);
        $sales = $this->accounts($companyId, 'sales')->firstWhere('id', (int) $data['sales_account_id']);

        if (! $receivable || ! $sales) {
            throw ValidationException::withMessages([
                'receivable_account_id' => 'Akun piutang atau penjualan tidak valid untuk tagihan toko.',
            ]);
        }

        $journal = DB::transaction(function () use ($data, $companyId, $branchId, $receivable, $sales) {
            $amount = (float) $data['amount'];
            $description = ($data['description'] ?? null) ?: 'Tagihan toko '.$data['store_name'];
            $journal = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transaction_date' => $data['transaction_date'],
                'journal_number' => $this->nextJournalNumber($companyId),
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $description,
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            $journal->details()->createMany([
                ['account_id' => $receivable->id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $sales->id, 'description' => $description, 'debit' => 0, 'credit' => $amount],
            ]);

            AuditLog::record('create_store_receivable', 'Tagihan Toko', null, $journal->load('details')->toArray(), $companyId, $branchId);

            return $journal;
        });

        TransactionAnomaly::recordIfNeeded('Tagihan Toko', [
            'journal_entry_id' => $journal->id,
            'reference_number' => $journal->reference_number,
        ], (float) $data['amount'], $journal->company_id, $journal->branch_id);

        return redirect()->route('journals.show', $journal)->with('success', 'Tagihan toko berhasil diposting ke GL.');
    }

    private function accounts(int $companyId, string $purpose)
    {
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->when($purpose === 'receivable', fn ($q) => $q->where(fn ($qq) => $qq->where('code', 'like', '120.%')->orWhere('name', 'like', '%Piutang Dagang%')))
            ->when($purpose === 'sales', fn ($q) => $q->where(fn ($qq) => $qq->where('code', 'like', '400.%')->orWhere('type', 'revenue')))
            ->orderBy('code')
            ->get();
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
