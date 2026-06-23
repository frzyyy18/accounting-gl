@extends('layouts.app', ['title' => 'Global Search'])

@section('content')
<div class="search-page-header mb-3">
    <form action="{{ route('search') }}" class="global-search global-search-page" role="search">
        <span data-icon="search" class="app-icon" aria-hidden="true"></span>
        <input name="q" value="{{ $query }}" class="form-control" placeholder="Cari jurnal, akun, transaksi..." autofocus>
        <button class="btn btn-primary">Cari</button>
    </form>
    @if($query !== '')
        <div class="text-muted small mt-2">{{ $totalResults }} hasil untuk "{{ $query }}"</div>
    @endif
</div>

@if($query === '')
    <div class="card p-4 text-center text-muted">Masukkan kata kunci untuk mencari jurnal, akun, atau transaksi kas/bank.</div>
@else
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card search-result-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Jurnal</span>
                <span class="badge text-bg-light border">{{ $results['journals']->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($results['journals'] as $journal)
                    <a href="{{ route('journals.show', $journal) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="fw-semibold">{{ $journal->journal_number }}</div>
                            @include('journals.partials.status-badge', ['status' => $journal->status])
                        </div>
                        <div class="search-result-meta">{{ $journal->transaction_date->format('d/m/Y') }} | {{ $journal->branch?->code ?: '-' }}</div>
                        <div class="search-result-text">{{ $journal->description ?: $journal->reference_number }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Tidak ada jurnal cocok.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card search-result-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Akun</span>
                <span class="badge text-bg-light border">{{ $results['accounts']->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($results['accounts'] as $account)
                    <a href="{{ route('accounts.index', ['search' => $account->code]) }}" class="list-group-item list-group-item-action">
                        <div class="fw-semibold">{{ $account->code }} - {{ $account->name }}</div>
                        <div class="search-result-meta">{{ \App\Models\Account::TYPES[$account->type] ?? $account->type }} | {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Tidak ada akun cocok.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card search-result-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Transaksi Kas/Bank</span>
                <span class="badge text-bg-light border">{{ $results['transactions']->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($results['transactions'] as $transaction)
                    <a href="{{ $transaction->journalEntry ? route('journals.show', $transaction->journalEntry) : route('cash-bank-transactions.index') }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="fw-semibold">{{ \App\Models\CashBankTransaction::TYPES[$transaction->type] ?? $transaction->type }}</div>
                            <span class="money">{{ rupiah($transaction->amount) }}</span>
                        </div>
                        <div class="search-result-meta">{{ $transaction->transaction_date->format('d/m/Y') }} | {{ $transaction->branch?->code ?: '-' }} | {{ $transaction->cashBank?->name }}</div>
                        <div class="search-result-text">{{ $transaction->description ?: $transaction->reference_number }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Tidak ada transaksi cocok.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif
@endsection
