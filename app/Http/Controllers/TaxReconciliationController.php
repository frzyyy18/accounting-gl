<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalDetail;
use App\Support\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaxReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $sortDirection = $this->sortDirection($request);
        $companyIds = $this->accessibleCompanyIds();
        $companyId = $this->selectedCompanyId($request, $companyIds);
        $companies = Company::whereIn('id', $companyIds)->orderBy('name')->get();
        $company = $companies->firstWhere('id', $companyId);
        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get();
        $branchId = $request->integer('branch_id') ?: null;

        $details = $this->postedProfitLossDetails($request, $companyId, $branchId);
        $income = $details
            ->whereIn('type', ['revenue', 'other_income'])
            ->sum(fn ($row) => (float) $row->credit - (float) $row->debit);
        $expense = $details
            ->whereIn('type', ['expense', 'other_expense'])
            ->sum(fn ($row) => (float) $row->debit - (float) $row->credit);
        $commercialProfit = $income - $expense;

        $corrections = $details
            ->map(fn ($row) => $this->correctionRow($row))
            ->filter(fn ($row) => abs($row->correction_amount) > 0.009)
            ->values();
        $positiveCorrections = $corrections->filter(fn ($row) => $row->correction_amount > 0)->values();
        $negativeCorrections = $corrections->filter(fn ($row) => $row->correction_amount < 0)->values();
        if ($sortDirection === 'newest') {
            $positiveCorrections = $positiveCorrections->sortByDesc(fn ($row) => $row->transaction_date.'-'.str_pad((string) $row->id, 12, '0', STR_PAD_LEFT))->values();
            $negativeCorrections = $negativeCorrections->sortByDesc(fn ($row) => $row->transaction_date.'-'.str_pad((string) $row->id, 12, '0', STR_PAD_LEFT))->values();
        }
        $positiveCorrectionTotal = $positiveCorrections->sum('correction_amount');
        $negativeCorrectionTotal = abs($negativeCorrections->sum('correction_amount'));
        $fiscalProfit = $commercialProfit + $positiveCorrectionTotal - $negativeCorrectionTotal;
        $estimatedCorporateTax = max($fiscalProfit, 0) * corporateTaxRate();

        $data = [
            'company' => $company,
            'companies' => $companies,
            'branches' => $branches,
            'commercialProfit' => $commercialProfit,
            'positiveCorrections' => $positiveCorrections,
            'negativeCorrections' => $negativeCorrections,
            'positiveCorrectionTotal' => $positiveCorrectionTotal,
            'negativeCorrectionTotal' => $negativeCorrectionTotal,
            'fiscalProfit' => $fiscalProfit,
            'estimatedCorporateTax' => $estimatedCorporateTax,
            'sortDirection' => $sortDirection,
            'pdfRows' => [
                'Perusahaan: '.$company?->name,
                'Periode: '.($request->from ?: 'Awal data').' - '.($request->to ?: 'Akhir data'),
                'Laba komersial: '.$this->money($commercialProfit),
                'Koreksi positif: '.$this->money($positiveCorrectionTotal),
                'Koreksi negatif: '.$this->money($negativeCorrectionTotal),
                'Laba fiskal: '.$this->money($fiscalProfit),
                'Estimasi PPh Badan '.number_format(corporateTaxRate() * 100, 0).'%: '.$this->money($estimatedCorporateTax),
            ],
        ];

        if ($request->export === 'excel') {
            return response(view('reports.tax.reconciliation', $data + ['print' => true])->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="rekonsiliasi-fiskal.xls"',
            ]);
        }

        if ($request->export === 'pdf') {
            return response(SimplePdf::table('Rekonsiliasi Fiskal', $data['pdfRows']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rekonsiliasi-fiskal.pdf"',
            ]);
        }

        return view('reports.tax.reconciliation', $data + ['print' => false]);
    }

    private function postedProfitLossDetails(Request $request, int $companyId, ?int $branchId = null): Collection
    {
        return JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($request->from, fn ($q) => $q->whereDate('journal_entries.transaction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('journal_entries.transaction_date', '<=', $request->to))
            ->whereIn('accounts.type', ['revenue', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('
                journal_details.id,
                journal_details.description,
                journal_details.debit,
                journal_details.credit,
                journal_details.fiscal_amount,
                journal_details.fiscal_note,
                journal_entries.transaction_date,
                journal_entries.journal_number,
                journal_entries.reference_number,
                accounts.code,
                accounts.name,
                accounts.type,
                accounts.fiscal_deductibility,
                accounts.is_non_deductible
            ')
            ->orderBy('journal_entries.transaction_date')
            ->orderBy('journal_details.id')
            ->get();
    }

    private function correctionRow(object $row): object
    {
        $incomeType = in_array($row->type, ['revenue', 'other_income'], true);
        $commercialAmount = $incomeType
            ? (float) $row->credit - (float) $row->debit
            : (float) $row->debit - (float) $row->credit;
        $commercialAmount = max($commercialAmount, 0);
        $fiscalAmount = $row->fiscal_amount !== null
            ? (float) $row->fiscal_amount
            : $this->defaultFiscalAmount($row, $commercialAmount, $incomeType);
        $commercialSigned = $incomeType ? $commercialAmount : -$commercialAmount;
        $fiscalSigned = $incomeType ? $fiscalAmount : -$fiscalAmount;

        return (object) [
            'id' => $row->id,
            'transaction_date' => $row->transaction_date,
            'journal_number' => $row->journal_number,
            'reference_number' => $row->reference_number,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'description' => $row->description,
            'commercial_amount' => $commercialAmount,
            'fiscal_amount' => $fiscalAmount,
            'correction_amount' => $fiscalSigned - $commercialSigned,
            'fiscal_note' => $row->fiscal_note,
            'is_non_deductible' => (bool) $row->is_non_deductible,
            'fiscal_deductibility' => (float) $row->fiscal_deductibility,
        ];
    }

    private function defaultFiscalAmount(object $row, float $commercialAmount, bool $incomeType): float
    {
        if (! $incomeType && (bool) $row->is_non_deductible) {
            return 0;
        }

        if (! $incomeType) {
            return $commercialAmount * ((float) $row->fiscal_deductibility / 100);
        }

        return $commercialAmount;
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

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
