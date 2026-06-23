@extends('layouts.app', ['title' => 'Neraca / Balance Sheet'])

@section('content')
<div class="card p-3">
    <h5>Neraca / Balance Sheet</h5>
    @include('reports.partials.filters')
    @php
        $assetTotal = $assets->sum(fn ($row) => $row->total_debit - $row->total_credit);
        $liabilityTotal = $liabilities->sum(fn ($row) => $row->total_credit - $row->total_debit);
        $equityTotal = $equities->sum(fn ($row) => $row->total_credit - $row->total_debit);
        $equityWithProfit = $equityTotal + $currentProfit;
    @endphp
    <div class="row g-3">
        <div class="col-lg-6">
            <table class="table table-bordered table-sm responsive-table">
                <thead><tr><th colspan="{{ ($compareRows ?? collect())->isNotEmpty() ? 4 : 3 }}">Aset / Assets</th></tr><tr><th>Kode</th><th>Nama Akun</th><th class="money">Saldo</th>@if(($compareRows ?? collect())->isNotEmpty())<th class="money">Pembanding</th>@endif</tr></thead>
                <tbody>
                @forelse($assets as $row)
                    @php $value = $row->total_debit - $row->total_credit; @endphp
                    @php $compare = ($compareRows ?? collect())->get($row->id); $compareValue = $compare ? $compare->total_debit - $compare->total_credit : null; @endphp
                    <tr><td data-label="Kode">{{ $row->code }}</td><td data-label="Nama Akun"><a href="{{ route('reports.ledger', request()->except(['account_id','export']) + ['account_id' => $row->id]) }}">{{ $row->name }}</a></td><td data-label="Saldo" class="money">{{ rupiah($value) }}</td>@if(($compareRows ?? collect())->isNotEmpty())<td data-label="Pembanding" class="money">{{ $compare ? rupiah($compareValue) : '-' }}</td>@endif</tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada saldo aset.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><th data-label="Ringkasan" colspan="2">Total Aset</th><th data-label="Saldo" class="money">{{ rupiah($assetTotal) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th></th>@endif</tr></tfoot>
            </table>
        </div>
        <div class="col-lg-6">
            <table class="table table-bordered table-sm responsive-table">
                <thead><tr><th colspan="{{ ($compareRows ?? collect())->isNotEmpty() ? 4 : 3 }}">Kewajiban / Liabilities</th></tr><tr><th>Kode</th><th>Nama Akun</th><th class="money">Saldo</th>@if(($compareRows ?? collect())->isNotEmpty())<th class="money">Pembanding</th>@endif</tr></thead>
                <tbody>
                @forelse($liabilities as $row)
                    @php $value = $row->total_credit - $row->total_debit; @endphp
                    @php $compare = ($compareRows ?? collect())->get($row->id); $compareValue = $compare ? $compare->total_credit - $compare->total_debit : null; @endphp
                    <tr><td data-label="Kode">{{ $row->code }}</td><td data-label="Nama Akun"><a href="{{ route('reports.ledger', request()->except(['account_id','export']) + ['account_id' => $row->id]) }}">{{ $row->name }}</a></td><td data-label="Saldo" class="money">{{ rupiah($value) }}</td>@if(($compareRows ?? collect())->isNotEmpty())<td data-label="Pembanding" class="money">{{ $compare ? rupiah($compareValue) : '-' }}</td>@endif</tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada saldo kewajiban.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><th data-label="Ringkasan" colspan="2">Total Kewajiban</th><th data-label="Saldo" class="money">{{ rupiah($liabilityTotal) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th></th>@endif</tr></tfoot>
            </table>

            <table class="table table-bordered table-sm responsive-table">
                <thead><tr><th colspan="{{ ($compareRows ?? collect())->isNotEmpty() ? 4 : 3 }}">Ekuitas / Equity</th></tr><tr><th>Kode</th><th>Nama Akun</th><th class="money">Saldo</th>@if(($compareRows ?? collect())->isNotEmpty())<th class="money">Pembanding</th>@endif</tr></thead>
                <tbody>
                @forelse($equities as $row)
                    @php $value = $row->total_credit - $row->total_debit; @endphp
                    @php $compare = ($compareRows ?? collect())->get($row->id); $compareValue = $compare ? $compare->total_credit - $compare->total_debit : null; @endphp
                    <tr><td data-label="Kode">{{ $row->code }}</td><td data-label="Nama Akun"><a href="{{ route('reports.ledger', request()->except(['account_id','export']) + ['account_id' => $row->id]) }}">{{ $row->name }}</a></td><td data-label="Saldo" class="money">{{ rupiah($value) }}</td>@if(($compareRows ?? collect())->isNotEmpty())<td data-label="Pembanding" class="money">{{ $compare ? rupiah($compareValue) : '-' }}</td>@endif</tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada saldo ekuitas.</td></tr>
                @endforelse
                    <tr><td data-label="Kode">-</td><td data-label="Nama Akun">Laba/Rugi Berjalan</td><td data-label="Saldo" class="money">{{ rupiah($currentProfit) }}</td>@if(($compareRows ?? collect())->isNotEmpty())<td></td>@endif</tr>
                </tbody>
                <tfoot><tr><th data-label="Ringkasan" colspan="2">Total Ekuitas</th><th data-label="Saldo" class="money">{{ rupiah($equityWithProfit) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th></th>@endif</tr></tfoot>
            </table>
        </div>
    </div>
    <div class="card p-3 bg-light">
        <div class="row">
            <div class="col-md-4"><div class="text-muted small">Total Aset</div><div class="h5 money">{{ rupiah($assetTotal) }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Kewajiban + Ekuitas</div><div class="h5 money">{{ rupiah($liabilityTotal + $equityWithProfit) }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Selisih</div><div class="h5 money">{{ rupiah($assetTotal - ($liabilityTotal + $equityWithProfit)) }}</div></div>
        </div>
    </div>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
