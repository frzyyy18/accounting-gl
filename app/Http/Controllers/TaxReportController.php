<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalDetail;
use App\Services\TaxQueryService;
use App\Support\SimplePdf;
use Illuminate\Http\Request;

class TaxReportController extends Controller
{
    public function __construct(private readonly TaxQueryService $taxService)
    {
    }

    public function index(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $companyIds = $this->accessibleCompanyIds();
        $companyId = $this->selectedCompanyId($request, $companyIds);

        $rows = JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.company_id', $companyId)
            ->when($request->branch_id, fn ($q) => $q->where('journal_entries.branch_id', $request->branch_id))
            ->when($request->from, fn ($q) => $q->whereDate('journal_entries.transaction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('journal_entries.transaction_date', '<=', $request->to))
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('accounts.id, accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code', $this->accountSortDirection($sortDirection))
            ->get();

        $income = $rows->whereIn('type', ['revenue', 'other_income'])->sum(fn ($row) => (float) $row->total_credit - (float) $row->total_debit);
        $expense = $rows->whereIn('type', ['expense', 'other_expense'])->sum(fn ($row) => (float) $row->total_debit - (float) $row->total_credit);
        $profitBeforeTax = $income - $expense;
        $branchId = $request->integer('branch_id') ?: null;
        $ppnRows = $this->taxService->taxAccountRows($companyId, 'ppn', $request->input('from'), $request->input('to'), $branchId);
        $pph21Rows = $this->taxService->taxAccountRows($companyId, 'pph21', $request->input('from'), $request->input('to'), $branchId);
        $pph23Rows = $this->taxService->taxAccountRows($companyId, 'pph23', $request->input('from'), $request->input('to'), $branchId);
        $pphFinalRows = $this->taxService->taxAccountRows($companyId, 'pph_final', $request->input('from'), $request->input('to'), $branchId);
        if ($sortDirection === 'newest') {
            $ppnRows = $ppnRows->sortByDesc('code')->values();
            $pph21Rows = $pph21Rows->sortByDesc('code')->values();
            $pph23Rows = $pph23Rows->sortByDesc('code')->values();
            $pphFinalRows = $pphFinalRows->sortByDesc('code')->values();
        }
        $ppnPayable = $ppnRows->sum(fn ($row) => $this->taxService->accountBalance($row));
        $pph21Payable = $pph21Rows->sum(fn ($row) => $this->taxService->accountBalance($row));
        $pph23Payable = $pph23Rows->sum(fn ($row) => $this->taxService->accountBalance($row));
        $pphFinalPayable = $pphFinalRows->sum(fn ($row) => $this->taxService->accountBalance($row));
        $uncategorizedTaxAccounts = $this->taxService->uncategorizedPostedTaxAccounts(
            [$companyId],
            $request->input('from'),
            $request->input('to'),
            $branchId
        );

        $company = Company::find($companyId);
        $data = [
            'company' => $company,
            'companies' => Company::whereIn('id', $companyIds)->orderBy('name')->get(),
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'rows' => $rows,
            'income' => $income,
            'expense' => $expense,
            'profitBeforeTax' => $profitBeforeTax,
            'estimatedCorporateTax' => max($profitBeforeTax, 0) * corporateTaxRate(),
            'ppnRows' => $ppnRows,
            'pph21Rows' => $pph21Rows,
            'pph23Rows' => $pph23Rows,
            'pphFinalRows' => $pphFinalRows,
            'ppnPayable' => $ppnPayable,
            'pph21Payable' => $pph21Payable,
            'pph23Payable' => $pph23Payable,
            'pphFinalPayable' => $pphFinalPayable,
            'uncategorizedTaxAccounts' => $uncategorizedTaxAccounts,
            'sortDirection' => $sortDirection,
            'pdfRows' => [
                'Perusahaan: '.$company?->name,
                'NPWP: '.($company?->tax_number ?: '-'),
                'Pendapatan fiskal awal: '.$this->money($income),
                'Beban fiskal awal: '.$this->money($expense),
                'Laba sebelum pajak: '.$this->money($profitBeforeTax),
                'PPN bersih: '.$this->money($ppnPayable),
                'PPh 21 bersih: '.$this->money($pph21Payable),
                'PPh 23 bersih: '.$this->money($pph23Payable),
                'PPh Final bersih: '.$this->money($pphFinalPayable),
                'Estimasi PPh Badan '.number_format(corporateTaxRate() * 100, 0).'%: '.$this->money(max($profitBeforeTax, 0) * corporateTaxRate()),
            ],
        ];

        if ($request->export === 'excel') {
            return response(view('reports.tax.index', $data + ['print' => true])->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="laporan-pajak.xls"',
            ]);
        }

        if ($request->export === 'pdf') {
            return response(SimplePdf::table('Laporan Pajak', $data['pdfRows']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-pajak.pdf"',
            ]);
        }

        return view('reports.tax.index', $data + ['print' => false]);
    }

    private function accessibleCompanyIds(): array
    {
        $user = auth()->user();

        if ($user->hasRole(['super_admin', 'manager_pajak'])) {
            return Company::orderBy('name')->pluck('id')->all();
        }

        return array_values(array_filter([(int) ($user->company_id ?: Company::orderBy('name')->value('id'))]));
    }

    private function selectedCompanyId(Request $request, array $companyIds): int
    {
        $requested = $request->integer('company_id');

        if ($requested && in_array($requested, $companyIds, true)) {
            return $requested;
        }

        return (int) ($companyIds[0] ?? auth()->user()->company_id);
    }

    private function sortDirection(Request $request): string
    {
        return $request->input('sort_direction') === 'oldest' ? 'oldest' : 'newest';
    }

    private function accountSortDirection(string $sortDirection): string
    {
        return $sortDirection === 'newest' ? 'desc' : 'asc';
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
