<?php

namespace App\Services;

use App\Models\JournalDetail;
use Illuminate\Support\Collection;

class TaxQueryService
{
    public function taxAccountRows(
        int $companyId,
        string $tax,
        ?string $from = null,
        ?string $to = null,
        ?int $branchId = null
    ): Collection {
        return JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($from, fn ($q) => $q->whereDate('journal_entries.transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.transaction_date', '<=', $to))
            ->where('accounts.tax_category', $tax)
            ->selectRaw('accounts.id, accounts.type, accounts.code, accounts.name, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();
    }

    public function taxBalance(array $companyIds, string $tax, string $start, string $end): float
    {
        return (float) JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->whereIn('journal_entries.company_id', $companyIds)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.transaction_date', '>=', $start)
            ->whereDate('journal_entries.transaction_date', '<=', $end)
            ->where('accounts.tax_category', $tax)
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.type IN ('asset', 'expense', 'other_expense') THEN journal_details.debit - journal_details.credit ELSE journal_details.credit - journal_details.debit END), 0) as total")
            ->value('total');
    }

    public function uncategorizedPostedTaxAccounts(
        array $companyIds,
        ?string $from = null,
        ?string $to = null,
        ?int $branchId = null
    ): Collection {
        return JournalDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_details.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->whereIn('journal_entries.company_id', $companyIds)
            ->where('journal_entries.status', 'posted')
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($from, fn ($q) => $q->whereDate('journal_entries.transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.transaction_date', '<=', $to))
            ->whereNull('accounts.tax_category')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, COUNT(journal_details.id) as posted_detail_count')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->filter(fn ($row) => $this->looksLikeTaxAccount($row))
            ->values();
    }

    public function accountBalance(object $row): float
    {
        if (in_array($row->type, ['asset', 'expense', 'other_expense'], true)) {
            return (float) $row->total_debit - (float) $row->total_credit;
        }

        return (float) $row->total_credit - (float) $row->total_debit;
    }

    private function looksLikeTaxAccount(object $row): bool
    {
        $text = strtoupper($row->code.' '.$row->name);
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $text) ?: '';

        return str_contains($normalized, 'PPN')
            || str_contains($normalized, 'PAJAK PERTAMBAHAN NILAI')
            || preg_match('/\bPPH\s*(FINAL|4)\b/', $normalized)
            || preg_match('/\bPPH\s*(PS|PASAL)?\s*21\b/', $normalized)
            || preg_match('/\bPPH\s*(PS|PASAL)?\s*23\b/', $normalized)
            || preg_match('/\bPPH(21|23)\b/', $normalized)
            || str_contains($normalized, 'PAJAK 21');
    }
}
