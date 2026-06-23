<?php

namespace App\Support;

use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CashBankMutation
{
    public static function currentBalance(CashBank $cashBank): float
    {
        $cashBanks = collect([$cashBank]);
        $balances = self::openingBalances($cashBank->company_id, $cashBanks);
        $rows = self::rows($cashBank->company_id, $cashBanks);
        $last = self::withRunningBalances($rows, $balances)->last();

        return $last ? (float) $last['balance'] : (float) ($balances[$cashBank->id] ?? 0);
    }

    public static function openingBalances(int $companyId, Collection $cashBanks, ?string $from = null, ?int $branchId = null): array
    {
        $balances = $cashBanks->mapWithKeys(fn (CashBank $cashBank) => [
            $cashBank->id => (float) $cashBank->opening_balance,
        ])->all();

        if (! $from) {
            return $balances;
        }

        $rows = self::rows($companyId, $cashBanks, null, Carbon::parse($from)->subDay()->toDateString(), $branchId);
        foreach ($rows as $row) {
            $cashBankId = $row['cash_bank']->id;
            $balances[$cashBankId] = ($balances[$cashBankId] ?? 0) + $row['debit'] - $row['credit'];
        }

        return $balances;
    }

    public static function rows(int $companyId, Collection $cashBanks, ?string $from = null, ?string $to = null, ?int $branchId = null): Collection
    {
        if ($cashBanks->isEmpty()) {
            return collect();
        }

        return self::transactionRows($companyId, $cashBanks, $from, $to, $branchId)
            ->merge(self::manualJournalRows($companyId, $cashBanks, $from, $to, $branchId))
            ->sortBy(fn (array $row) => $row['date']->format('Ymd').'-'.str_pad((string) $row['sequence'], 12, '0', STR_PAD_LEFT))
            ->values();
    }

    public static function withRunningBalances(Collection $rows, array $balances): Collection
    {
        return $rows->map(function (array $row) use (&$balances) {
            $cashBankId = $row['cash_bank']->id;
            $balances[$cashBankId] = ($balances[$cashBankId] ?? 0) + $row['debit'] - $row['credit'];
            $row['balance'] = array_sum($balances);

            return $row;
        });
    }

    private static function transactionRows(int $companyId, Collection $cashBanks, ?string $from, ?string $to, ?int $branchId): Collection
    {
        $ids = $cashBanks->pluck('id');

        return CashBankTransaction::with('branch', 'cashBank.account', 'targetCashBank.account', 'counterAccount', 'journalEntry.branch')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->where(fn ($q) => $q->whereIn('cash_bank_id', $ids)->orWhereIn('target_cash_bank_id', $ids))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (CashBankTransaction $transaction) => self::cashBankTransactionMovements($transaction, $ids, $branchId));
    }

    private static function cashBankTransactionMovements(CashBankTransaction $transaction, Collection $cashBankIds, ?int $branchId = null): array
    {
        $ids = $cashBankIds->map(fn ($id) => (int) $id);
        $movements = [];

        if ($ids->contains((int) $transaction->cash_bank_id)) {
            $isDebit = in_array($transaction->type, ['cash_in', 'bank_in'], true);
            $movements[] = self::row(
                $transaction->transaction_date,
                $transaction->id * 10,
                $transaction->cashBank,
                $transaction->reference_number ?: $transaction->journalEntry?->reference_number ?: $transaction->journalEntry?->journal_number,
                $transaction->description ?: CashBankTransaction::TYPES[$transaction->type],
                $transaction->targetCashBank?->name ?? trim(($transaction->counterAccount?->code.' '.$transaction->counterAccount?->name)) ?: '-',
                $isDebit ? (float) $transaction->amount : 0,
                $isDebit ? 0 : (float) $transaction->amount,
                $transaction->journalEntry,
                $transaction->type === 'transfer' ? 'transfer_out' : $transaction->type,
                'transaction',
                $transaction->id
            );
        }

        if ($transaction->type === 'transfer' && $ids->contains((int) $transaction->target_cash_bank_id)) {
            $movements[] = self::row(
                $transaction->transaction_date,
                $transaction->id * 10 + 1,
                $transaction->targetCashBank,
                $transaction->reference_number ?: $transaction->journalEntry?->reference_number ?: $transaction->journalEntry?->journal_number,
                $transaction->description ?: CashBankTransaction::TYPES[$transaction->type],
                $transaction->cashBank?->name ?: '-',
                (float) $transaction->amount,
                0,
                $transaction->journalEntry,
                'transfer_in',
                'transaction',
                $transaction->id
            );
        }

        return array_values(array_filter(
            $movements,
            fn (array $movement) => $movement['cash_bank']
                && self::matchesBranch($movement['cash_bank'], (int) $transaction->branch_id, $branchId)
        ));
    }

    private static function manualJournalRows(int $companyId, Collection $cashBanks, ?string $from, ?string $to, ?int $branchId): Collection
    {
        $cashBanksByAccount = $cashBanks
            ->filter(fn (CashBank $cashBank) => $cashBank->account_id)
            ->groupBy('account_id');

        if ($cashBanksByAccount->isEmpty()) {
            return collect();
        }

        return JournalEntry::with('branch', 'details.account')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->whereHas('details', fn ($q) => $q->whereIn('account_id', $cashBanksByAccount->keys()))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cash_bank_transactions')
                    ->whereColumn('cash_bank_transactions.journal_entry_id', 'journal_entries.id')
                    ->whereNull('cash_bank_transactions.deleted_at');
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->flatMap(function (JournalEntry $journal) use ($cashBanksByAccount, $branchId) {
                return $journal->details
                    ->filter(fn ($detail) => $cashBanksByAccount->has($detail->account_id))
                    ->flatMap(function ($detail) use ($journal, $cashBanksByAccount, $branchId) {
                        $amount = (float) $detail->debit - (float) $detail->credit;
                        if ($amount === 0.0) {
                            return [];
                        }

                        $opposite = $journal->details
                            ->reject(fn ($other) => (int) $other->id === (int) $detail->id)
                            ->map(fn ($other) => trim(($other->account?->code.' '.$other->account?->name)) ?: null)
                            ->filter()
                            ->unique()
                            ->implode(', ');

                        $matchingCashBanks = $cashBanksByAccount[$detail->account_id];

                        return $matchingCashBanks
                            ->filter(fn (CashBank $cashBank) => ($cashBank->scope ?? 'company') !== 'branch'
                                || $matchingCashBanks->count() === 1
                                || (int) $cashBank->branch_id === (int) $journal->branch_id)
                            ->filter(fn (CashBank $cashBank) => self::matchesBranch($cashBank, (int) $journal->branch_id, $branchId))
                            ->map(fn (CashBank $cashBank) => self::row(
                                $journal->transaction_date,
                                $journal->id * 10 + $detail->id,
                                $cashBank,
                                $journal->reference_number ?: $journal->journal_number,
                                $detail->description ?: $journal->description,
                                $opposite ?: '-',
                                $amount > 0 ? $amount : 0,
                                $amount < 0 ? abs($amount) : 0,
                                $journal,
                                $amount > 0 ? 'manual_in' : 'manual_out',
                                'journal_detail',
                                $detail->id
                            ));
                    })
                    ->filter();
            });
    }

    private static function matchesBranch(CashBank $cashBank, int $transactionBranchId, ?int $filterBranchId): bool
    {
        if (! $filterBranchId) {
            return true;
        }

        return $transactionBranchId === (int) $filterBranchId
            || (($cashBank->scope ?? 'company') === 'branch' && (int) $cashBank->branch_id === (int) $filterBranchId);
    }

    private static function row($date, int $sequence, ?CashBank $cashBank, ?string $reference, ?string $description, string $opposite, float $debit, float $credit, ?JournalEntry $journal, ?string $movementType = null, ?string $sourceType = null, ?int $sourceId = null): array
    {
        return [
            'date' => $date instanceof Carbon ? $date : Carbon::parse($date),
            'sequence' => $sequence,
            'cash_bank' => $cashBank,
            'reference' => $reference ?: '-',
            'description' => $description ?: '-',
            'opposite' => $opposite,
            'debit' => $debit,
            'credit' => $credit,
            'journal' => $journal,
            'balance' => 0,
            'movement_type' => $movementType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_key' => $sourceType && $sourceId ? "{$sourceType}:{$sourceId}" : null,
        ];
    }
}
