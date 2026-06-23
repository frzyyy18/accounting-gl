<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashBankTransaction;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $companyId = $this->selectedCompanyId($request);
        $results = [
            'journals' => collect(),
            'accounts' => collect(),
            'transactions' => collect(),
        ];

        if ($query !== '') {
            if (auth()->user()->canManage('journal.view')) {
                $results['journals'] = JournalEntry::with('branch')
                    ->where('company_id', $companyId)
                    ->where(function ($q) use ($query) {
                        $q->where('journal_number', 'like', "%{$query}%")
                            ->orWhere('reference_number', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('status', 'like', "%{$query}%");
                    })
                    ->latest('transaction_date')
                    ->limit(8)
                    ->get();
            }

            if (auth()->user()->canManage('account.view')) {
                $results['accounts'] = Account::where('company_id', $companyId)
                    ->where(function ($q) use ($query) {
                        $q->where('code', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%")
                            ->orWhere('type', 'like', "%{$query}%");
                    })
                    ->orderBy('code')
                    ->limit(8)
                    ->get();
            }

            if (auth()->user()->canManage('cash_transaction.view')) {
                $results['transactions'] = CashBankTransaction::with('branch', 'cashBank', 'targetCashBank', 'counterAccount', 'journalEntry')
                    ->where('company_id', $companyId)
                    ->where(function ($q) use ($query) {
                        $q->where('reference_number', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('type', 'like', "%{$query}%")
                            ->orWhereHas('cashBank', fn ($qq) => $qq->where('name', 'like', "%{$query}%"))
                            ->orWhereHas('targetCashBank', fn ($qq) => $qq->where('name', 'like', "%{$query}%"))
                            ->orWhereHas('counterAccount', fn ($qq) => $qq->where('code', 'like', "%{$query}%")->orWhere('name', 'like', "%{$query}%"))
                            ->orWhereHas('journalEntry', fn ($qq) => $qq->where('journal_number', 'like', "%{$query}%"));
                    })
                    ->latest('transaction_date')
                    ->limit(8)
                    ->get();
            }
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
            'totalResults' => collect($results)->sum(fn ($items) => $items->count()),
        ]);
    }

    private function selectedCompanyId(Request $request): int
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return (int) ($user->company_id ?: Company::orderBy('name')->value('id'));
        }

        $requested = $request->integer('company_id');
        if ($requested && Company::whereKey($requested)->exists()) {
            return $requested;
        }

        return (int) ($user->company_id ?: Company::orderBy('name')->value('id'));
    }
}
