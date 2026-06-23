<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\AuditLog as AuditLogModel;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\Company;
use App\Models\JournalDetail;
use App\Support\CashBankMutation;
use App\Support\SimplePdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function ledger(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $details = $this->detailQuery($request)
            ->when($request->account_id, fn ($q) => $q->where('account_id', $request->account_id))
            ->when(! $request->account_id, fn ($q) => $q->orderBy('accounts.code'))
            ->orderBy('journal_entries.transaction_date')
            ->orderBy('journal_details.id')
            ->get();
        $details = $this->withRunningBalances($request, $details);
        if ($sortDirection === 'newest') {
            $details = $details
                ->sortByDesc(fn ($detail) => $detail->transaction_date.'-'.str_pad((string) $detail->id, 12, '0', STR_PAD_LEFT))
                ->values();
        }

        AuditLog::record($request->export ? 'export' : 'view', 'General Ledger');

        if ($request->export === 'excel') {
            [$company, $period, $sections] = $this->generalLedgerPdfData($request, $details);

            return response($this->generalLedgerExcelHtml($company, $period, $sections), 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="buku-besar.xls"',
            ]);
        }

        if ($request->export === 'pdf') {
            [$company, $period, $sections] = $this->generalLedgerPdfData($request, $details);

            return response(SimplePdf::generalLedger($company, $period, $sections), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="buku-besar.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.ledger', [
            'details' => $details,
            'accounts' => $this->accounts($request),
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'running' => 0,
            'pdfRows' => $details->map(fn ($detail) => implode(' | ', [
                $detail->transaction_date,
                $detail->journal_number,
                $detail->code.' '.$detail->name,
                'D '.$this->money($detail->debit),
                'K '.$this->money($detail->credit),
                'Saldo '.$this->money($detail->running_balance),
                $detail->description,
            ]))->all(),
        ], 'buku-besar', 'Buku Besar');
    }

    public function trialBalance(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $rows = $this->baseDetailQuery($request)
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code', $this->accountSortDirection($sortDirection))
            ->get();

        AuditLog::record($request->export ? 'export' : 'view', 'Trial Balance');

        $compareRows = $this->comparisonRows($request, ['asset', 'liability', 'equity', 'revenue', 'expense', 'other_income', 'other_expense']);

        if ($request->export === 'pdf') {
            return response(SimplePdf::trialBalanceReport(
                $this->reportCompanyName($request),
                $this->reportPeriodLabel($request),
                $this->trialBalancePdfRows($rows, $compareRows),
                rupiah($rows->sum('total_debit')),
                rupiah($rows->sum('total_credit')),
                $compareRows->isNotEmpty()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="neraca-saldo.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.trial-balance', [
            'rows' => $rows,
            'compareRows' => $compareRows,
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'pdfRows' => $rows->map(fn ($row) => implode(' | ', [
                $row->code,
                $row->name,
                'Debit '.$this->money($row->total_debit),
                'Kredit '.$this->money($row->total_credit),
            ]))->all(),
        ], 'neraca-saldo', 'Neraca Saldo');
    }

    public function profitLoss(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $rows = $this->baseDetailQuery($request)
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('accounts.id, accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code', $this->accountSortDirection($sortDirection))
            ->get();

        $income = $rows->whereIn('type', ['revenue', 'other_income'])->sum(fn ($row) => $row->total_credit - $row->total_debit);
        $expense = $rows->whereIn('type', ['expense', 'other_expense'])->sum(fn ($row) => $row->total_debit - $row->total_credit);

        AuditLog::record($request->export ? 'export' : 'view', 'Profit & Loss');

        $compareRows = $this->comparisonRows($request, ['revenue', 'expense', 'other_income', 'other_expense']);

        if ($request->export === 'pdf') {
            return response(SimplePdf::profitLossReport(
                $this->reportCompanyName($request),
                $this->reportPeriodLabel($request),
                $this->profitLossPdfRows($rows, $compareRows),
                rupiah($income),
                rupiah($expense),
                rupiah($income - $expense),
                $compareRows->isNotEmpty()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laba-rugi.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.profit-loss', [
            'rows' => $rows,
            'compareRows' => $compareRows,
            'income' => $income,
            'expense' => $expense,
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'pdfRows' => $rows->map(fn ($row) => implode(' | ', [
                $row->code,
                $row->name,
                $this->money(in_array($row->type, ['revenue', 'other_income'], true) ? $row->total_credit - $row->total_debit : $row->total_debit - $row->total_credit),
            ]))->all(),
        ], 'laba-rugi', 'Laba Rugi');
    }

    public function balanceSheet(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $rows = $this->baseDetailQuery($request)
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->selectRaw('accounts.id, accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code', $this->accountSortDirection($sortDirection))
            ->get();

        $profitRows = $this->baseDetailQuery($request)
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('accounts.type, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.type')
            ->get();

        $income = $profitRows->whereIn('type', ['revenue', 'other_income'])->sum(fn ($row) => $row->total_credit - $row->total_debit);
        $expense = $profitRows->whereIn('type', ['expense', 'other_expense'])->sum(fn ($row) => $row->total_debit - $row->total_credit);
        $currentProfit = $income - $expense;

        $assets = $rows->where('type', 'asset')->values();
        $liabilities = $rows->where('type', 'liability')->values();
        $equities = $rows->where('type', 'equity')->values();

        AuditLog::record($request->export ? 'export' : 'view', 'Balance Sheet');

        $compareRows = $this->comparisonRows($request, ['asset', 'liability', 'equity']);

        if ($request->export === 'pdf') {
            return response(SimplePdf::balanceSheetReport(
                $this->reportCompanyName($request),
                $this->reportPeriodLabel($request),
                $this->balanceSheetPdfSections($assets, $liabilities, $equities, $currentProfit, $compareRows),
                $this->balanceSheetPdfSummary($assets, $liabilities, $equities, $currentProfit),
                $compareRows->isNotEmpty()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="neraca.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.balance-sheet', [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equities' => $equities,
            'currentProfit' => $currentProfit,
            'compareRows' => $compareRows,
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'pdfRows' => $rows->map(fn ($row) => implode(' | ', [
                strtoupper($row->type),
                $row->code,
                $row->name,
                $this->money(in_array($row->type, ['asset'], true) ? $row->total_debit - $row->total_credit : $row->total_credit - $row->total_debit),
            ]))->all(),
        ], 'neraca', 'Neraca');
    }

    public function cashFlow(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $companyId = $this->selectedCompanyId($request);
        $from = $request->from;
        $to = $request->to;

        $cashBanks = CashBank::where('company_id', $companyId)
            ->when($request->branch_id, fn ($q) => $q->where(fn ($qq) => $qq->where('scope', 'company')->orWhere('branch_id', $request->branch_id)))
            ->get();

        $branchId = $request->integer('branch_id') ?: null;
        $openingBalance = array_sum(CashBankMutation::openingBalances($companyId, $cashBanks, $from, $branchId));
        $periodRows = CashBankMutation::rows($companyId, $cashBanks, $from, $to, $branchId);
        $displayRows = $sortDirection === 'newest' ? $periodRows->reverse()->values() : $periodRows;

        $cashIn = (float) $periodRows->whereIn('movement_type', ['cash_in', 'bank_in', 'manual_in'])->sum('debit');
        $cashOut = (float) $periodRows->whereIn('movement_type', ['cash_out', 'manual_out'])->sum('credit');
        $transferIn = (float) $periodRows->where('movement_type', 'transfer_in')->sum('debit');
        $transferOut = (float) $periodRows->where('movement_type', 'transfer_out')->sum('credit');
        $endingBalance = $openingBalance + $cashIn - $cashOut + $transferIn - $transferOut;

        AuditLog::record($request->export ? 'export' : 'view', 'Cash Flow');

        if ($request->export === 'pdf') {
            return response(SimplePdf::cashFlowReport(
                $this->reportCompanyName($request),
                $this->reportPeriodLabel($request),
                $this->cashFlowPdfSummary($openingBalance, $cashIn, $cashOut, $transferIn, $transferOut, $endingBalance),
                $this->cashFlowPdfRows($displayRows)
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="arus-kas.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.cash-flow', [
            'openingBalance' => $openingBalance,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'transferIn' => $transferIn,
            'transferOut' => $transferOut,
            'endingBalance' => $endingBalance,
            'transactions' => $displayRows,
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'pdfRows' => [
                'Saldo awal: '.$this->money($openingBalance),
                'Kas masuk: '.$this->money($cashIn),
                'Kas keluar: '.$this->money($cashOut),
                'Transfer masuk: '.$this->money($transferIn),
                'Transfer keluar: '.$this->money($transferOut),
                'Saldo akhir: '.$this->money($endingBalance),
            ],
        ], 'arus-kas', 'Arus Kas');
    }

    public function cashFlowIndirect(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $profitRows = $this->baseDetailQuery($request)
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('accounts.type, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.type')
            ->get();

        $income = $profitRows->whereIn('type', ['revenue', 'other_income'])->sum(fn ($row) => $row->total_credit - $row->total_debit);
        $expense = $profitRows->whereIn('type', ['expense', 'other_expense'])->sum(fn ($row) => $row->total_debit - $row->total_credit);
        $netIncome = $income - $expense;

        $workingCapitalRows = $this->baseDetailQuery($request)
            ->whereIn('accounts.type', ['asset', 'liability'])
            ->whereNotIn('accounts.id', \App\Models\CashBank::where('company_id', $this->selectedCompanyId($request))->pluck('account_id')->filter()->values())
            ->selectRaw('accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code', $this->accountSortDirection($sortDirection))
            ->get();

        $adjustments = $workingCapitalRows->map(function ($row) {
            $balance = $row->type === 'asset'
                ? (float) $row->total_debit - (float) $row->total_credit
                : (float) $row->total_credit - (float) $row->total_debit;

            return (object) [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'balance' => $balance,
                'cash_effect' => $row->type === 'asset' ? -$balance : $balance,
            ];
        });

        $operatingCashFlow = $netIncome + $adjustments->sum('cash_effect');

        AuditLog::record($request->export ? 'export' : 'view', 'Cash Flow Indirect');

        if ($request->export === 'pdf') {
            return response(SimplePdf::cashFlowIndirectReport(
                $this->reportCompanyName($request),
                $this->reportPeriodLabel($request),
                rupiah($netIncome),
                $this->cashFlowIndirectPdfRows($adjustments),
                rupiah($operatingCashFlow)
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="arus-kas-tidak-langsung.pdf"',
            ]);
        }

        return $this->renderOrExport($request, 'reports.cash-flow-indirect', [
            'netIncome' => $netIncome,
            'adjustments' => $adjustments,
            'operatingCashFlow' => $operatingCashFlow,
            'branches' => $this->branches($request),
            'sortDirection' => $sortDirection,
            'pdfRows' => array_merge(
                ['Laba bersih: '.$this->money($netIncome)],
                $adjustments->map(fn ($row) => $row->code.' '.$row->name.' | '.$this->money($row->cash_effect))->all(),
                ['Arus kas operasi: '.$this->money($operatingCashFlow)]
            ),
        ], 'arus-kas-tidak-langsung', 'Arus Kas Tidak Langsung');
    }

    public function auditTrail(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $logs = AuditLogModel::with('user')
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $this->selectedCompanyId($request)))
            ->orderBy('created_at', $sortDirection === 'newest' ? 'desc' : 'asc')
            ->orderBy('id', $sortDirection === 'newest' ? 'desc' : 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('reports.audit-trail', compact('logs', 'sortDirection'));
    }

    private function detailQuery(Request $request)
    {
        return $this->baseDetailQuery($request)
            ->leftJoin('branches', 'branches.id', '=', 'journal_entries.branch_id')
            ->select('journal_details.*', 'journal_entries.transaction_date', 'journal_entries.branch_id', 'journal_entries.journal_number', 'journal_entries.reference_number', 'journal_entries.description as journal_description', 'branches.code as branch_code', 'accounts.code', 'accounts.name', 'accounts.type');
    }

    private function baseDetailQuery(Request $request)
    {
        return JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journal_entries.company_id', $this->selectedCompanyId($request))
            ->where('journal_entries.status', $request->status ?: 'posted')
            ->when($request->branch_id, fn ($q) => $q->where('journal_entries.branch_id', $request->branch_id))
            ->when($request->from, fn ($q) => $q->whereDate('journal_entries.transaction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('journal_entries.transaction_date', '<=', $request->to));
    }

    private function accounts(Request $request)
    {
        return Account::where('company_id', $this->selectedCompanyId($request))->orderBy('code')->get();
    }

    private function branches(Request $request)
    {
        return Branch::where('company_id', $this->selectedCompanyId($request))
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function renderOrExport(Request $request, string $view, array $data, string $filename, ?string $title = null)
    {
        $data += [
            'companies' => $this->companies(),
            'selectedCompanyId' => $this->selectedCompanyId($request),
        ];

        if ($request->export === 'excel') {
            $html = view($view, $data + ['print' => true])->render();

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
            ]);
        }

        if ($request->export === 'pdf') {
            return response(SimplePdf::table($title ?? $filename, $data['pdfRows'] ?? []), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]);
        }

        return view($view, $data + ['print' => false]);
    }

    private function trialBalancePdfRows($rows, $compareRows): array
    {
        return $rows->map(function ($row) use ($compareRows) {
            $net = (float) $row->total_debit - (float) $row->total_credit;
            $compare = $compareRows->get($row->id);
            $columns = [
                $row->code,
                $row->name,
                rupiah($row->total_debit),
                rupiah($row->total_credit),
                $net > 0 ? rupiah($net) : '-',
                $net < 0 ? rupiah(abs($net)) : '-',
            ];

            if ($compareRows->isNotEmpty()) {
                $columns[] = $compare
                    ? rupiah((float) $compare->total_debit - (float) $compare->total_credit)
                    : '-';
            }

            return $columns;
        })->all();
    }

    private function cashFlowPdfSummary(float $openingBalance, float $cashIn, float $cashOut, float $transferIn, float $transferOut, float $endingBalance): array
    {
        return [
            'Saldo Awal' => rupiah($openingBalance),
            'Kas Masuk' => rupiah($cashIn),
            'Kas Keluar' => rupiah($cashOut),
            'Transfer Masuk' => rupiah($transferIn),
            'Transfer Keluar' => rupiah($transferOut),
            'Saldo Akhir' => rupiah($endingBalance),
        ];
    }

    private function cashFlowPdfRows($transactions): array
    {
        return $transactions->map(fn ($transaction) => [
            $transaction['date']->format('d/m/Y'),
            $transaction['journal']?->branch?->code ?: '-',
            $this->cashFlowTypeLabel((string) $transaction['movement_type']),
            $transaction['reference'] ?: '-',
            $transaction['description'] ?: '-',
            (float) $transaction['debit'] > 0 ? rupiah($transaction['debit']) : '-',
            (float) $transaction['credit'] > 0 ? rupiah($transaction['credit']) : '-',
        ])->all();
    }

    private function cashFlowTypeLabel(string $movementType): string
    {
        return [
            'cash_in' => 'Kas Masuk',
            'bank_in' => 'Bank Masuk',
            'cash_out' => 'Kas Keluar',
            'transfer_in' => 'Transfer Masuk',
            'transfer_out' => 'Transfer Keluar',
            'manual_in' => 'Jurnal Manual Masuk',
            'manual_out' => 'Jurnal Manual Keluar',
        ][$movementType] ?? 'Mutasi Kas/Bank';
    }

    private function cashFlowIndirectPdfRows($adjustments): array
    {
        return $adjustments->map(fn ($row) => [
            data_get($row, 'code'),
            data_get($row, 'name'),
            data_get($row, 'type'),
            rupiah(data_get($row, 'cash_effect', 0)),
        ])->all();
    }

    private function profitLossPdfRows($rows, $compareRows): array
    {
        return $rows->map(function ($row) use ($compareRows) {
            $value = in_array($row->type, ['revenue', 'other_income'], true)
                ? (float) $row->total_credit - (float) $row->total_debit
                : (float) $row->total_debit - (float) $row->total_credit;
            $compare = $compareRows->get($row->id);
            $compareValue = $compare
                ? (in_array($row->type, ['revenue', 'other_income'], true)
                    ? (float) $compare->total_credit - (float) $compare->total_debit
                    : (float) $compare->total_debit - (float) $compare->total_credit)
                : null;

            $columns = [
                $row->code,
                $row->name,
                Account::TYPES[$row->type] ?? $row->type,
                rupiah($value),
            ];

            if ($compareRows->isNotEmpty()) {
                $columns[] = $compare ? rupiah($compareValue) : '-';
                $columns[] = $compare ? rupiah($value - $compareValue) : '-';
            }

            return $columns;
        })->all();
    }

    private function balanceSheetPdfSections($assets, $liabilities, $equities, float $currentProfit, $compareRows): array
    {
        $assetRows = $this->balanceSheetPdfRows($assets, $compareRows, fn ($row) => (float) $row->total_debit - (float) $row->total_credit, 'asset');
        $liabilityRows = $this->balanceSheetPdfRows($liabilities, $compareRows, fn ($row) => (float) $row->total_credit - (float) $row->total_debit, 'liability');
        $equityRows = $this->balanceSheetPdfRows($equities, $compareRows, fn ($row) => (float) $row->total_credit - (float) $row->total_debit, 'equity');
        $equityRows[] = $compareRows->isNotEmpty()
            ? ['-', 'Laba/Rugi Berjalan', rupiah($currentProfit), '']
            : ['-', 'Laba/Rugi Berjalan', rupiah($currentProfit)];

        $assetTotal = $assets->sum(fn ($row) => (float) $row->total_debit - (float) $row->total_credit);
        $liabilityTotal = $liabilities->sum(fn ($row) => (float) $row->total_credit - (float) $row->total_debit);
        $equityTotal = $equities->sum(fn ($row) => (float) $row->total_credit - (float) $row->total_debit) + $currentProfit;

        return [
            ['title' => 'ASET / ASSETS', 'rows' => $assetRows, 'total_label' => 'Total Aset', 'total' => rupiah($assetTotal)],
            ['title' => 'KEWAJIBAN / LIABILITIES', 'rows' => $liabilityRows, 'total_label' => 'Total Kewajiban', 'total' => rupiah($liabilityTotal)],
            ['title' => 'EKUITAS / EQUITY', 'rows' => $equityRows, 'total_label' => 'Total Ekuitas', 'total' => rupiah($equityTotal)],
        ];
    }

    private function balanceSheetPdfRows($rows, $compareRows, callable $valueResolver, string $type): array
    {
        return $rows->map(function ($row) use ($compareRows, $valueResolver, $type) {
            $value = $valueResolver($row);
            $compare = $compareRows->get($row->id);

            $columns = [$row->code, $row->name, rupiah($value)];

            if ($compareRows->isNotEmpty()) {
                $compareValue = null;
                if ($compare) {
                    $compareValue = $type === 'asset'
                        ? (float) $compare->total_debit - (float) $compare->total_credit
                        : (float) $compare->total_credit - (float) $compare->total_debit;
                }
                $columns[] = $compare ? rupiah($compareValue) : '-';
            }

            return $columns;
        })->all();
    }

    private function balanceSheetPdfSummary($assets, $liabilities, $equities, float $currentProfit): array
    {
        $assetTotal = $assets->sum(fn ($row) => (float) $row->total_debit - (float) $row->total_credit);
        $liabilityTotal = $liabilities->sum(fn ($row) => (float) $row->total_credit - (float) $row->total_debit);
        $equityTotal = $equities->sum(fn ($row) => (float) $row->total_credit - (float) $row->total_debit);
        $equityWithProfit = $equityTotal + $currentProfit;

        return [
            'Total Aset' => rupiah($assetTotal),
            'Kewajiban + Ekuitas' => rupiah($liabilityTotal + $equityWithProfit),
            'Selisih' => rupiah($assetTotal - ($liabilityTotal + $equityWithProfit)),
        ];
    }

    private function reportCompanyName(Request $request): string
    {
        return Company::find($this->selectedCompanyId($request))?->name ?: config('app.name');
    }

    private function reportPeriodLabel(Request $request): string
    {
        $from = $request->from ? Carbon::parse($request->from)->format('d M Y') : 'Awal';
        $to = $request->to ? Carbon::parse($request->to)->format('d M Y') : 'Akhir';

        return "Periode: {$from} - {$to}";
    }

    private function sortDirection(Request $request): string
    {
        return $request->input('sort_direction') === 'oldest' ? 'oldest' : 'newest';
    }

    private function accountSortDirection(string $sortDirection): string
    {
        return $sortDirection === 'newest' ? 'desc' : 'asc';
    }

    private function comparisonRows(Request $request, array $types)
    {
        if (! $request->compare_from && ! $request->compare_to) {
            return collect();
        }

        $compareRequest = new Request($request->except(['from', 'to']) + [
            'from' => $request->compare_from,
            'to' => $request->compare_to,
        ]);
        $compareRequest->setUserResolver(fn () => $request->user());

        return $this->baseDetailQuery($compareRequest)
            ->whereIn('accounts.type', $types)
            ->selectRaw('accounts.id, accounts.type, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type')
            ->get()
            ->keyBy('id');
    }

    private function withRunningBalances(Request $request, $details)
    {
        $balances = $this->openingBalances($request)->all();

        return $details->map(function ($detail) use (&$balances) {
            $accountId = (int) $detail->account_id;
            $balances[$accountId] = ($balances[$accountId] ?? 0)
                + $this->signedAmount((float) $detail->debit, (float) $detail->credit, $detail->type);
            $detail->running_balance = $balances[$accountId];

            return $detail;
        });
    }

    private function openingBalances(Request $request)
    {
        if (! $request->from) {
            return collect();
        }

        $openingRequest = new Request($request->except(['from', 'to', 'compare_from', 'compare_to']));
        $openingRequest->setUserResolver(fn () => $request->user());

        return $this->baseDetailQuery($openingRequest)
            ->when($request->account_id, fn ($q) => $q->where('account_id', $request->account_id))
            ->whereDate('journal_entries.transaction_date', '<', $request->from)
            ->selectRaw('accounts.id, accounts.type, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->id => $this->signedAmount((float) $row->total_debit, (float) $row->total_credit, $row->type),
            ]);
    }

    private function signedAmount(float $debit, float $credit, string $type): float
    {
        return in_array($type, ['asset', 'expense', 'other_expense'], true)
            ? $debit - $credit
            : $credit - $debit;
    }

    private function generalLedgerPdfData(Request $request, $details): array
    {
        $company = Company::find($this->selectedCompanyId($request))?->name ?: config('app.name');
        $from = $request->from ? Carbon::parse($request->from)->format('d M Y') : 'Awal';
        $to = $request->to ? Carbon::parse($request->to)->format('d M Y') : 'Akhir';
        $sections = [];

        foreach ($details->groupBy('account_id') as $accountDetails) {
            $chronologicalDetails = $accountDetails
                ->sortBy(fn ($detail) => $detail->transaction_date.'-'.str_pad((string) $detail->id, 12, '0', STR_PAD_LEFT))
                ->values();
            $first = $chronologicalDetails->first();
            $periodDebit = (float) $accountDetails->sum('debit');
            $periodCredit = (float) $accountDetails->sum('credit');
            $firstMovement = $this->signedAmount((float) $first->debit, (float) $first->credit, $first->type);
            $openingBalance = (float) $first->running_balance - $firstMovement;
            $closingBalance = (float) $chronologicalDetails->last()->running_balance;

            $rows = [];

            foreach ($accountDetails as $detail) {
                $description = $detail->description ?: $detail->journal_description;

                $rows[] = [
                    'type' => $this->journalTypeCode($detail),
                    'date' => Carbon::parse($detail->transaction_date)->format('d M y'),
                    'description' => $description,
                    'department' => $detail->branch_code ?: '',
                    'voucher' => $detail->reference_number ?: $detail->journal_number,
                    'debit' => (float) $detail->debit == 0.0 ? '' : $this->pdfMoney((float) $detail->debit),
                    'credit' => (float) $detail->credit == 0.0 ? '' : $this->pdfMoney((float) $detail->credit),
                    'balance' => $this->pdfMoney((float) $detail->running_balance),
                ];
            }

            $sections[] = [
                'account' => trim($first->code.' '.$first->name),
                'opening_balance' => $this->pdfMoney($openingBalance),
                'period_debit' => $this->pdfMoney($periodDebit),
                'period_credit' => $this->pdfMoney($periodCredit),
                'closing_balance' => $this->pdfMoney($closingBalance),
                'rows' => $rows,
            ];
        }

        return [$company, "From {$from} to {$to}", $sections];
    }

    private function generalLedgerExcelHtml(string $company, string $period, array $sections): string
    {
        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                color: #000000;
                margin: 0;
                padding: 16px;
                background: #ffffff;
            }
            .report-header {
                text-align: center;
                margin-bottom: 14px;
                padding: 10px;
                background: #e8eef6;
                border-top: 3px solid #3366aa;
                border-bottom: 2px solid #3366aa;
            }
            .report-header .company-name {
                font-size: 14px;
                font-weight: bold;
                color: #000000;
            }
            .report-header .report-title {
                font-size: 12px;
                font-weight: bold;
                color: #000000;
                margin-top: 3px;
            }
            .report-header .report-period {
                font-size: 10px;
                color: #000000;
                margin-top: 3px;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 8px;
            }
            th, td {
                border: 1px solid #999999;
                padding: 4px 8px;
                vertical-align: top;
                color: #000000;
            }
            tr.col-header th {
                background: #c5d5ea;
                color: #000000;
                font-weight: bold;
                font-size: 10px;
                text-align: left;
                white-space: nowrap;
                border: 1px solid #7799bb;
            }
            tr.col-header th.money { text-align: right; }
            tr.account td {
                background: #d6e4f0;
                color: #000000;
                font-weight: bold;
                font-size: 11px;
                border: 1px solid #7799bb;
                padding: 5px 8px;
            }
            tr.opening td { background: #f2f6fb; color: #000000; font-style: italic; }
            tr.total td   { background: #ddeeff; color: #000000; font-weight: bold; border-top: 2px solid #3366aa; }
            tr.data-even td { background: #ffffff; color: #000000; }
            tr.data-odd  td { background: #f2f6fb; color: #000000; }
            tr.spacer td    { border: none; height: 5px; background: #ffffff; color: #000000; }
            .money { text-align: right; white-space: nowrap; font-family: "Courier New", monospace; color: #000000; }
            .negative { color: #000000; font-weight: bold; }
        </style></head><body>';

        // ── Report header block ───────────────────────────────────────────────
        $html .= '<div class="report-header">'
            . '<div class="company-name">' . e($company) . '</div>'
            . '<div class="report-title">GENERAL LEDGER</div>'
            . '<div class="report-period">' . e($period) . '</div>'
            . '</div>';

        $html .= '<table>';

        // ── Column headers ────────────────────────────────────────────────────
        $html .= '<tr class="col-header">'
            . '<th style="width:52px;color:#000000">Type</th>'
            . '<th style="width:78px;color:#000000">Date</th>'
            . '<th style="width:260px;color:#000000">Description</th>'
            . '<th style="width:110px;color:#000000">Department</th>'
            . '<th style="width:130px;color:#000000">Voucher No</th>'
            . '<th class="money" style="width:110px;color:#000000">Debit</th>'
            . '<th class="money" style="width:110px;color:#000000">Credit</th>'
            . '<th class="money" style="width:120px;color:#000000">Balance</th>'
            . '</tr>';

        if ($sections === []) {
            $html .= '<tr><td colspan="8" style="padding:12px;color:#000000;font-style:italic;background:#ffffff">Tidak ada transaksi pada filter ini.</td></tr>';
        }

        foreach ($sections as $section) {
            // ── Account heading ───────────────────────────────────────────────
            $html .= '<tr class="account"><td colspan="8">' . e($section['account']) . '</td></tr>';

            // ── Opening balance ───────────────────────────────────────────────
            $html .= '<tr class="opening">'
                . '<td></td><td></td><td><em>Opening Balance</em></td><td></td><td></td>'
                . '<td class="money"></td>'
                . '<td class="money"></td>'
                . '<td class="money">' . e($section['opening_balance']) . '</td>'
                . '</tr>';

            // ── Transaction rows ──────────────────────────────────────────────
            $rowIdx = 0;
            foreach ($section['rows'] as $row) {
                $cls     = $rowIdx % 2 === 0 ? 'data-even' : 'data-odd';
                $debit   = $row['debit']   ?? '';
                $credit  = $row['credit']  ?? '';
                $balance = $row['balance'] ?? '';
                $negBal  = str_starts_with((string)$balance, '(') ? ' negative' : '';

                $html .= '<tr class="' . $cls . '">'
                    . '<td>' . e($row['type']        ?? '') . '</td>'
                    . '<td>' . e($row['date']        ?? '') . '</td>'
                    . '<td>' . e($row['description'] ?? '') . '</td>'
                    . '<td>' . e($row['department']  ?? '') . '</td>'
                    . '<td>' . e($row['voucher']     ?? '') . '</td>'
                    . '<td class="money">' . e($debit)   . '</td>'
                    . '<td class="money">' . e($credit)  . '</td>'
                    . '<td class="money' . $negBal . '">' . e($balance) . '</td>'
                    . '</tr>';
                $rowIdx++;
            }

            // ── Period totals ─────────────────────────────────────────────────
            $html .= '<tr class="total">'
                . '<td></td><td></td><td>Period Totals / Closing Balance</td><td></td><td></td>'
                . '<td class="money">' . e($section['period_debit'])   . '</td>'
                . '<td class="money">' . e($section['period_credit'])  . '</td>'
                . '<td class="money">' . e($section['closing_balance']) . '</td>'
                . '</tr>';

            $html .= '<tr class="spacer"><td colspan="8"></td></tr>';
        }

        return $html . '</table></body></html>';
    }

    private function journalTypeCode(object $detail): string
    {
        $number = strtoupper((string) ($detail->journal_number ?? ''));
        $reference = strtoupper((string) ($detail->reference_number ?? ''));

        if (str_contains($number.$reference, 'OP')) {
            return 'OP';
        }

        return 'JV';
    }

    private function pdfMoney(float $value): string
    {
        $formatted = number_format(abs($value), 2, ',', '.');

        return $value < 0 ? "({$formatted})" : $formatted;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function selectedCompanyId(Request $request): int
    {
        $user = auth()->user();

        if (! $user->hasRole('super_admin')) {
            return (int) ($user->company_id ?: Company::orderBy('name')->value('id'));
        }

        $requested = $request->integer('company_id');
        if ($requested && Company::whereKey($requested)->exists()) {
            return $requested;
        }

        return (int) ($user->company_id ?: Company::orderBy('name')->value('id'));
    }

    private function companies()
    {
        return Company::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }
}
