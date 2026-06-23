<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\Company;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (auth()->user()->isTaxDivision()) {
            return app(DashboardTaxController::class)->index(request());
        }

        $companyIds = $this->visibleCompanyIds();
        $month = now()->format('Y-m');
        $periodStart = now()->startOfMonth()->subMonths(5);

        $base = fn ($q) => $q->whereIn('company_id', $companyIds)
            ->where('status', 'posted')
            ->where('transaction_date', 'like', "$month%");

        $income = JournalDetail::whereHas('journalEntry', $base)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['revenue', 'other_income']))
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as total')->value('total');

        $expense = JournalDetail::whereHas('journalEntry', $base)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['expense', 'other_expense']))
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as total')->value('total');

        $monthlyLabels = collect(range(5, 0))
            ->map(fn ($index) => now()->startOfMonth()->subMonths($index))
            ->mapWithKeys(fn (Carbon $date) => [$date->format('Y-m') => $date->translatedFormat('M Y')]);

        $monthlySummary = $monthlyLabels->map(fn ($label) => [
            'label' => $label,
            'income' => 0,
            'expense' => 0,
        ]);

        $details = JournalDetail::with(['journalEntry', 'account'])
            ->whereHas('journalEntry', fn ($q) => $q->whereIn('company_id', $companyIds)
                ->where('status', 'posted')
                ->whereDate('transaction_date', '>=', $periodStart))
            ->get();

        foreach ($details as $detail) {
            $periodKey = $detail->journalEntry->transaction_date->format('Y-m');

            if (! $monthlySummary->has($periodKey)) {
                continue;
            }

            $row = $monthlySummary->get($periodKey);

            if (in_array($detail->account->type, ['revenue', 'other_income'], true)) {
                $row['income'] += (float) $detail->credit - (float) $detail->debit;
            }

            if (in_array($detail->account->type, ['expense', 'other_expense'], true)) {
                $row['expense'] += (float) $detail->debit - (float) $detail->credit;
            }

            $monthlySummary->put($periodKey, $row);
        }

        $statusCounts = JournalEntry::whereIn('company_id', $companyIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $pendingApproval = JournalEntry::whereIn('company_id', $companyIds)
            ->where('status', 'submitted')
            ->when(auth()->user()->canManage('journal.approve'), fn ($q) => $q->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', auth()->id())), fn ($q) => $q->whereRaw('1 = 0'))
            ->count();

        $accountComposition = Account::whereIn('company_id', $companyIds)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('dashboard', [
            'income' => $income,
            'expense' => $expense,
            'profit' => $income - $expense,
            'pending' => $pendingApproval,
            'postedThisMonth' => JournalEntry::whereIn('company_id', $companyIds)->where('status', 'posted')->where('transaction_date', 'like', "$month%")->count(),
            'activeAccounts' => Account::whereIn('company_id', $companyIds)->where('is_active', true)->count(),
            'monthlyChart' => [
                'labels' => $monthlySummary->pluck('label')->values(),
                'income' => $monthlySummary->pluck('income')->map(fn ($value) => round($value, 2))->values(),
                'expense' => $monthlySummary->pluck('expense')->map(fn ($value) => round($value, 2))->values(),
                'profit' => $monthlySummary->map(fn ($row) => round($row['income'] - $row['expense'], 2))->values(),
            ],
            'statusChart' => [
                'labels' => collect(['draft', 'submitted', 'approved', 'posted', 'rejected', 'cancelled'])->map(fn ($status) => strtoupper($status)),
                'values' => collect(['draft', 'submitted', 'approved', 'posted', 'rejected', 'cancelled'])->map(fn ($status) => (int) ($statusCounts[$status] ?? 0)),
            ],
            'accountChart' => [
                'labels' => collect(Account::TYPES)->map(fn ($label) => strtok($label, ' /'))->values(),
                'values' => collect(array_keys(Account::TYPES))->map(fn ($type) => (int) ($accountComposition[$type] ?? 0))->values(),
            ],
            'recentJournals' => JournalEntry::with('creator', 'company')->whereIn('company_id', $companyIds)->latest()->limit(8)->get(),
        ]);
    }

    private function visibleCompanyIds(): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            return [(int) auth()->user()->company_id];
        }

        return Company::pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
