<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingEntryController extends Controller
{
    public function create(Request $request)
    {
        abort_unless(auth()->user()->canManage('closing.create'), 403);
        $preview = null;
        $selectedPeriod = null;
        $selectedEquity = null;

        if ($request->filled(['fiscal_period_id', 'equity_account_id'])) {
            $selectedPeriod = FiscalPeriod::where('company_id', auth()->user()->company_id)->findOrFail($request->integer('fiscal_period_id'));
            $selectedEquity = Account::where('company_id', auth()->user()->company_id)->where('type', 'equity')->findOrFail($request->integer('equity_account_id'));
            $preview = $this->closingPreview($selectedPeriod, $selectedEquity);
        }

        return view('closing-entries.create', [
            'periods' => FiscalPeriod::where('company_id', auth()->user()->company_id)->orderByDesc('start_date')->get(),
            'equityAccounts' => Account::where('company_id', auth()->user()->company_id)->where('type', 'equity')->where('is_active', true)->orderBy('code')->get(),
            'preview' => $preview,
            'selectedPeriod' => $selectedPeriod,
            'selectedEquity' => $selectedEquity,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canManage('closing.create'), 403);

        $data = $request->validate([
            'fiscal_period_id' => ['required', 'integer'],
            'equity_account_id' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'confirm_preview' => ['accepted'],
        ]);

        $period = FiscalPeriod::where('company_id', auth()->user()->company_id)->findOrFail($data['fiscal_period_id']);
        $equity = Account::where('company_id', auth()->user()->company_id)->where('type', 'equity')->findOrFail($data['equity_account_id']);
        $reference = 'CLOSING-'.$period->id;

        if (JournalEntry::where('company_id', auth()->user()->company_id)->where('reference_number', $reference)->exists()) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'Closing entry untuk periode ini sudah pernah dibuat.']);
        }

        $preview = $this->closingPreview($period, $equity);
        $details = $preview['details'];
        $netIncome = $preview['netIncome'];

        if (empty($details) || round($netIncome, 2) == 0.0) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'Tidak ada saldo pendapatan/beban yang perlu ditutup.']);
        }

        $details[] = [
            'account_id' => $equity->id,
            'description' => 'Laba/Rugi ditutup ke '.$equity->name,
            'debit' => $netIncome < 0 ? abs($netIncome) : 0,
            'credit' => $netIncome > 0 ? $netIncome : 0,
        ];

        $journal = DB::transaction(function () use ($period, $data, $details, $reference) {
            $journal = JournalEntry::create([
                'company_id' => auth()->user()->company_id,
                'branch_id' => null,
                'transaction_date' => $period->end_date,
                'journal_number' => $this->nextJournalNumber(),
                'reference_number' => $reference,
                'description' => $data['description'] ?: 'Closing Entry '.$period->name,
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);
            $journal->details()->createMany($details);
            $period->update(['status' => 'closed']);
            AuditLog::record('closing_entry', 'Closing Entry', null, $journal->load('details')->toArray(), $journal->company_id);

            return $journal;
        });

        return redirect()->route('journals.show', $journal)->with('success', 'Closing entry berhasil dibuat dan periode ditutup.');
    }

    private function closingPreview(FiscalPeriod $period, Account $equity): array
    {
        $rows = \App\Models\JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journal_entries.company_id', auth()->user()->company_id)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.transaction_date', [$period->start_date, $period->end_date])
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('accounts.id as account_id, accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as debit, SUM(journal_details.credit) as credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        $details = [];
        $displayRows = [];
        $netIncome = 0.0;

        foreach ($rows as $row) {
            if (in_array($row->type, ['revenue', 'other_income'], true)) {
                $balance = (float) $row->credit - (float) $row->debit;
                if ($balance > 0) {
                    $details[] = ['account_id' => $row->account_id, 'description' => 'Closing '.$row->name, 'debit' => $balance, 'credit' => 0];
                    $displayRows[] = (object) ['code' => $row->code, 'name' => $row->name, 'type' => $row->type, 'debit' => $balance, 'credit' => 0];
                    $netIncome += $balance;
                }
            } else {
                $balance = (float) $row->debit - (float) $row->credit;
                if ($balance > 0) {
                    $details[] = ['account_id' => $row->account_id, 'description' => 'Closing '.$row->name, 'debit' => 0, 'credit' => $balance];
                    $displayRows[] = (object) ['code' => $row->code, 'name' => $row->name, 'type' => $row->type, 'debit' => 0, 'credit' => $balance];
                    $netIncome -= $balance;
                }
            }
        }

        return [
            'details' => $details,
            'rows' => $displayRows,
            'netIncome' => $netIncome,
            'equityRow' => (object) [
                'code' => $equity->code,
                'name' => $equity->name,
                'debit' => $netIncome < 0 ? abs($netIncome) : 0,
                'credit' => $netIncome > 0 ? $netIncome : 0,
            ],
        ];
    }

    private function nextJournalNumber(): string
    {
        $prefix = 'CL-'.now()->year.'-';
        $last = JournalEntry::where('company_id', auth()->user()->company_id)->where('journal_number', 'like', "$prefix%")->max('journal_number');
        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
