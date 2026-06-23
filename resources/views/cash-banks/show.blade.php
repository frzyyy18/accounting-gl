@extends('layouts.app', ['title' => 'Mutasi '.$cashBank->name])

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card p-3"><div class="text-muted small">{{ $cashBank->kind === 'bank' ? 'Bank' : 'Kas' }}</div><div class="h5 mb-0">{{ $cashBank->name }}</div></div></div>
    <div class="col-md-4"><div class="card p-3"><div class="text-muted small">Cabang</div><div class="h5 mb-0">{{ $cashBank->branch?->name ?: 'Perusahaan' }}</div></div></div>
    <div class="col-md-4"><div class="card p-3"><div class="text-muted small">Saldo Saat Ini</div><div class="h5 mb-0">{{ rupiah($cashBank->currentBalance()) }}</div></div></div>
</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0">Mutasi {{ $cashBank->kind === 'bank' ? 'Bank' : 'Kas' }}</h5>
        <a href="{{ route('cash-bank-transactions.index', ['kind' => $cashBank->kind, 'cash_bank_id' => $cashBank->id]) }}" class="btn btn-outline-primary btn-sm">Lihat di Menu Mutasi</a>
    </div>
    <table class="table table-hover responsive-table">
        <thead><tr><th>Tanggal</th><th>Voucher Nomor</th><th>Deskripsi</th><th>Lawan</th><th class="money">Debit</th><th class="money">Credit</th><th class="money">Balance</th><th>Jurnal</th></tr></thead>
        <tbody>
        @forelse($mutationRows as $row)
            <tr>
                <td data-label="Tanggal">{{ $row['date']->format('d/m/Y') }}</td>
                <td data-label="Voucher Nomor">{{ $row['reference'] }}</td>
                <td data-label="Deskripsi">{{ $row['description'] }}</td>
                <td data-label="Lawan">{{ $row['opposite'] }}</td>
                <td data-label="Debit" class="money">{{ $row['debit'] > 0 ? rupiah($row['debit']) : '-' }}</td>
                <td data-label="Credit" class="money">{{ $row['credit'] > 0 ? rupiah($row['credit']) : '-' }}</td>
                <td data-label="Balance" class="money">{{ rupiah($row['balance']) }}</td>
                <td data-label="Jurnal">@if($row['journal'])<a href="{{ route('journals.show',$row['journal']) }}">{{ $row['journal']->reference_number ?: $row['journal']->journal_number }}</a>@else - @endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted">Belum ada mutasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
