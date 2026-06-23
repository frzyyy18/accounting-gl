<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Services\TaxQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardTaxController extends Controller
{
    public function __construct(private readonly TaxQueryService $taxService)
    {
    }

    public function index(Request $request)
    {
        $period = $this->selectedPeriod($request);
        $companies = $this->taxCompanies();
        $selectedCompanyId = $this->selectedCompanyId($request, $companies);
        $companyIds = $selectedCompanyId
            ? [$selectedCompanyId]
            : $companies->pluck('id')->map(fn ($id) => (int) $id)->all();
        $journalStatus = $request->input('journal_status', 'action');
        $allowedStatuses = ['action', 'submitted', 'posted'];
        if (! in_array($journalStatus, $allowedStatuses, true)) {
            $journalStatus = 'action';
        }

        $selectedCompanies = $companies->whereIn('id', $companyIds)->values();
        $currentProfitLoss = $this->profitLossTotals($companyIds, $period['start'], $period['end']);
        $income = $currentProfitLoss['income'];
        $expense = $currentProfitLoss['expense'];
        $profitBeforeTax = $currentProfitLoss['profit'];

        $selectedDate = Carbon::create($period['year'], $period['month'], 1);
        $prevStart = $selectedDate->copy()->subMonth()->startOfMonth()->toDateString();
        $prevEnd = $selectedDate->copy()->subMonth()->endOfMonth()->toDateString();
        $prevProfitLoss = $this->profitLossTotals($companyIds, $prevStart, $prevEnd);

        $ppnPayable = $this->taxService->taxBalance($companyIds, 'ppn', $period['start'], $period['end']);
        $pph21Payable = $this->taxService->taxBalance($companyIds, 'pph21', $period['start'], $period['end']);
        $pph23Payable = $this->taxService->taxBalance($companyIds, 'pph23', $period['start'], $period['end']);
        $pphFinalPayable = $this->taxService->taxBalance($companyIds, 'pph_final', $period['start'], $period['end']);
        $ppnPrev = $this->taxService->taxBalance($companyIds, 'ppn', $prevStart, $prevEnd);
        $pph21Prev = $this->taxService->taxBalance($companyIds, 'pph21', $prevStart, $prevEnd);
        $pph23Prev = $this->taxService->taxBalance($companyIds, 'pph23', $prevStart, $prevEnd);
        $pphFinalPrev = $this->taxService->taxBalance($companyIds, 'pph_final', $prevStart, $prevEnd);
        $incomePrev = $prevProfitLoss['income'];
        $profitPrev = $prevProfitLoss['profit'];

        $draftOldCount = JournalEntry::whereIn('company_id', $companyIds)
            ->where('status', 'draft')
            ->where('created_at', '<', now()->subDays(3))
            ->count();
        $rejectedCount = JournalEntry::whereIn('company_id', $companyIds)
            ->where('status', 'rejected')
            ->count();
        $needActionJournals = JournalEntry::with('company', 'branch')
            ->withSum('details as total_amount', 'debit')
            ->whereIn('company_id', $companyIds)
            ->where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('status', 'draft')->where('created_at', '<', now()->subDays(3)))
                ->orWhere('status', 'rejected'))
            ->latest()
            ->limit(10)
            ->get();
        $taxTrend = $this->taxTrend($companyIds, $selectedDate);

        $recentJournals = JournalEntry::with('company', 'branch')
            ->withSum('details as total_amount', 'debit')
            ->whereIn('company_id', $companyIds)
            ->when($journalStatus === 'action', fn ($q) => $q->where(fn ($qq) => $qq
                ->where(fn ($r) => $r->where('status', 'draft')->where('created_at', '<', now()->subDays(3)))
                ->orWhere('status', 'rejected')))
            ->when($journalStatus !== 'action' && $journalStatus, fn ($q) => $q->where('status', $journalStatus))
            ->latest()
            ->limit(15)
            ->get();
        $pendingApprovalCount = JournalEntry::whereIn('company_id', $companyIds)
            ->where('status', 'submitted')
            ->whereDate('transaction_date', '>=', $period['start'])
            ->whereDate('transaction_date', '<=', $period['end'])
            ->when(auth()->user()->canManage('journal.approve'), fn ($q) => $q->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', auth()->id())), fn ($q) => $q->whereRaw('1 = 0'))
            ->count();
        $pendingApprovalTotal = JournalEntry::whereIn('company_id', $companyIds)
            ->where('status', 'submitted')
            ->when(auth()->user()->canManage('journal.approve'), fn ($q) => $q->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', auth()->id())), fn ($q) => $q->whereRaw('1 = 0'))
            ->count();
        $uncategorizedTaxAccounts = $this->taxService->uncategorizedPostedTaxAccounts($companyIds, $period['start'], $period['end']);

        return view('dashboards.tax', [
            'companies' => $companies,
            'canSelectAllCompanies' => auth()->user()->hasRole(['super_admin', 'manager_pajak']),
            'selectedCompanyId' => $selectedCompanyId,
            'selectedMonth' => $period['month'],
            'selectedYear' => $period['year'],
            'selectedMonthName' => $period['label'],
            'period' => $period,
            'journalStatus' => $journalStatus,
            'companyCount' => $selectedCompanies->count(),
            'branchCount' => Branch::whereIn('company_id', $companyIds)->where('is_active', true)->count(),
            'npwpMissing' => $selectedCompanies->filter(fn ($company) => blank($company->tax_number))->count(),
            'postedThisMonth' => JournalEntry::whereIn('company_id', $companyIds)
                ->where('status', 'posted')
                ->whereDate('transaction_date', '>=', $period['start'])
                ->whereDate('transaction_date', '<=', $period['end'])
                ->count(),
            'pendingApprovalCount' => $pendingApprovalCount,
            'pendingApprovalTotal' => $pendingApprovalTotal,
            'income' => $income,
            'expense' => $expense,
            'profitBeforeTax' => $profitBeforeTax,
            'estimatedCorporateTax' => max($profitBeforeTax, 0) * corporateTaxRate(),
            'ppnPayable' => $ppnPayable,
            'pph21Payable' => $pph21Payable,
            'pph23Payable' => $pph23Payable,
            'pphFinalPayable' => $pphFinalPayable,
            'ppnPrev' => $ppnPrev,
            'pph21Prev' => $pph21Prev,
            'pph23Prev' => $pph23Prev,
            'pphFinalPrev' => $pphFinalPrev,
            'incomePrev' => $incomePrev,
            'profitPrev' => $profitPrev,
            'taxTrend' => $taxTrend,
            'draftOldCount' => $draftOldCount,
            'rejectedCount' => $rejectedCount,
            'needActionJournals' => $needActionJournals,
            'uncategorizedTaxAccounts' => $uncategorizedTaxAccounts,
            'recentJournals' => $recentJournals,
        ]);
    }

    private function selectedPeriod(Request $request): array
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $month = $month >= 1 && $month <= 12 ? $month : now()->month;
        $year = $year >= now()->year - 10 && $year <= now()->year + 10 ? $year : now()->year;
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'month' => $month,
            'year' => $year,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('F'),
        ];
    }

    private function taxCompanies()
    {
        $user = auth()->user();

        return Company::query()
            ->when(! $user->hasRole(['super_admin', 'manager_pajak']), fn ($q) => $q->where('id', $user->company_id ?: Company::orderBy('name')->value('id')))
            ->orderBy('name')
            ->get();
    }

    private function selectedCompanyId(Request $request, $companies): ?int
    {
        if (auth()->user()->hasRole(['super_admin', 'manager_pajak'])
            && (! $request->has('company_id') || $request->input('company_id') === '')) {
            return null;
        }

        $requested = $request->integer('company_id');
        if ($requested && $companies->contains('id', $requested)) {
            return $requested;
        }

        $userCompanyId = (int) auth()->user()->company_id;
        if ($userCompanyId && $companies->contains('id', $userCompanyId)) {
            return $userCompanyId;
        }

        return (int) $companies->first()?->id;
    }

    private function profitLossTotals(array $companyIds, string $start, string $end): array
    {
        $base = fn ($q) => $q->whereIn('company_id', $companyIds)
            ->where('status', 'posted')
            ->whereDate('transaction_date', '>=', $start)
            ->whereDate('transaction_date', '<=', $end);

        $income = (float) JournalDetail::whereHas('journalEntry', $base)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['revenue', 'other_income']))
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as total')
            ->value('total');

        $expense = (float) JournalDetail::whereHas('journalEntry', $base)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['expense', 'other_expense']))
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as total')
            ->value('total');

        return [
            'income' => $income,
            'expense' => $expense,
            'profit' => $income - $expense,
        ];
    }

    private function taxTrend(array $companyIds, Carbon $selectedDate): array
    {
        $start = $selectedDate->copy()->subMonths(5)->startOfMonth();
        $end = $selectedDate->copy()->endOfMonth();
        $taxBalanceExpression = "CASE WHEN accounts.type IN ('asset', 'expense', 'other_expense') THEN journal_details.debit - journal_details.credit ELSE journal_details.credit - journal_details.debit END";

        $totals = JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->whereIn('journal_entries.company_id', $companyIds)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.transaction_date', '>=', $start->toDateString())
            ->whereDate('journal_entries.transaction_date', '<=', $end->toDateString())
            ->selectRaw('SUBSTR(journal_entries.transaction_date, 1, 7) as month_key')
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.type IN ('revenue', 'other_income') THEN journal_details.credit - journal_details.debit ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.type IN ('expense', 'other_expense') THEN journal_details.debit - journal_details.credit ELSE 0 END), 0) as expense")
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.tax_category = 'ppn' THEN {$taxBalanceExpression} ELSE 0 END), 0) as ppn")
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.tax_category = 'pph21' THEN {$taxBalanceExpression} ELSE 0 END), 0) as pph21")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        return collect(range(5, 0))->map(function ($monthsAgo) use ($selectedDate, $totals) {
            $month = $selectedDate->copy()->subMonths($monthsAgo);
            $row = $totals->get($month->format('Y-m'));
            $income = (float) ($row->income ?? 0);
            $expense = (float) ($row->expense ?? 0);

            return [
                'label' => $month->locale('id')->translatedFormat('M Y'),
                'ppn' => (float) ($row->ppn ?? 0),
                'pph21' => (float) ($row->pph21 ?? 0),
                'profit' => $income - $expense,
            ];
        })->values()->all();
    }

}
