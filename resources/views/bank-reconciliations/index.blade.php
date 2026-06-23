@extends('layouts.app', ['title' => 'Rekonsiliasi Bank'])

@section('content')
<div class="card p-3">
    <div class="d-flex justify-content-between mb-3"><h5>Rekonsiliasi Bank</h5><a href="{{ route('bank-reconciliations.create') }}" class="btn btn-primary btn-sm">Buat Rekonsiliasi</a></div>
    <table class="table table-hover">
        <thead><tr><th>Tanggal Rekening Koran</th><th>Bank</th><th>Cabang</th><th class="money">Saldo Bank</th><th class="money">Saldo Buku</th><th class="money">Selisih</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($items as $item)<tr><td>{{ $item->statement_date->format('d/m/Y') }}</td><td>{{ $item->cashBank?->name }}</td><td>{{ $item->branch?->code }}</td><td class="money">{{ rupiah($item->bank_statement_balance) }}</td><td class="money">{{ rupiah($item->book_balance) }}</td><td class="money">{{ rupiah($item->difference) }}</td><td>{{ strtoupper($item->status) }}</td><td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('bank-reconciliations.show',$item) }}">Detail</a></td></tr>@empty<tr><td colspan="8" class="text-center text-muted">Belum ada rekonsiliasi.</td></tr>@endforelse</tbody>
    </table>
    {{ $items->links() }}
</div>
@endsection
