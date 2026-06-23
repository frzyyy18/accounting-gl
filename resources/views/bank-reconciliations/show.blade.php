@extends('layouts.app', ['title' => 'Detail Rekonsiliasi Bank'])

@section('content')
<div class="card p-4">
    <h5>{{ $item->cashBank?->name }} - {{ $item->statement_date->format('d/m/Y') }}</h5>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="text-muted small">Saldo Bank</div><div class="h5 money">{{ rupiah($item->bank_statement_balance) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Saldo Buku</div><div class="h5 money">{{ rupiah($item->book_balance) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Selisih</div><div class="h5 money">{{ rupiah($item->difference) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Status</div><div class="h5">{{ strtoupper($item->status) }}</div></div>
    </div>
    <table class="table table-sm table-bordered">
        <thead><tr><th>Tanggal</th><th>Tipe</th><th>Referensi</th><th>Keterangan</th><th class="money">Nominal</th></tr></thead>
        <tbody>
            @foreach($item->transactions as $transaction)
                <tr><td>{{ $transaction->transaction_date->format('d/m/Y') }}</td><td>{{ \App\Models\CashBankTransaction::TYPES[$transaction->type] }}</td><td>{{ $transaction->reference_number }}</td><td>{{ $transaction->description }}</td><td class="money">{{ rupiah($transaction->amount) }}</td></tr>
            @endforeach
            @foreach($journalDetails as $detail)
                @php $nominal = (float) $detail->debit > 0 ? (float) $detail->debit : (float) $detail->credit; @endphp
                <tr><td>{{ $detail->journalEntry?->transaction_date?->format('d/m/Y') }}</td><td>Jurnal Manual</td><td>{{ $detail->journalEntry?->reference_number ?: $detail->journalEntry?->journal_number }}</td><td>{{ $detail->description ?: $detail->journalEntry?->description }}</td><td class="money">{{ rupiah($nominal) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
