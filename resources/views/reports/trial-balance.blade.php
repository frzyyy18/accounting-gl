@extends('layouts.app', ['title' => 'Neraca Saldo / Trial Balance'])

@section('content')
@include('reports.partials.filters')

<div class="card report-result-card">
    <div class="card-header">
        <div>
            <div class="card-title mb-0">Neraca Saldo / Trial Balance</div>
            <div class="card-subtitle">{{ $rows->count() }} akun ditampilkan</div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm responsive-table report-table mb-0">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="money">Total Debit</th>
                        <th class="money">Total Kredit</th>
                        <th class="money">Saldo Debit</th>
                        <th class="money">Saldo Kredit</th>
                        @if(($compareRows ?? collect())->isNotEmpty())<th class="money">Saldo Pembanding</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $net = $row->total_debit - $row->total_credit;
                            $compare = ($compareRows ?? collect())->get($row->id);
                            $compareNet = $compare ? $compare->total_debit - $compare->total_credit : null;
                        @endphp
                        <tr>
                            <td data-label="Kode Akun">{{ $row->code }}</td>
                            <td data-label="Nama Akun"><a href="{{ route('reports.ledger', request()->except(['account_id','export']) + ['account_id' => $row->id]) }}">{{ $row->name }}</a></td>
                            <td data-label="Total Debit" class="money amount-positive">{{ rupiah($row->total_debit) }}</td>
                            <td data-label="Total Kredit" class="money amount-positive">{{ rupiah($row->total_credit) }}</td>
                            <td data-label="Saldo Debit" class="money {{ $net > 0 ? 'amount-positive' : '' }}">{{ $net > 0 ? rupiah($net) : '-' }}</td>
                            <td data-label="Saldo Kredit" class="money {{ $net < 0 ? 'amount-negative' : '' }}">{{ $net < 0 ? rupiah(abs($net)) : '-' }}</td>
                            @if(($compareRows ?? collect())->isNotEmpty())<td data-label="Saldo Pembanding" class="money {{ $compareNet < 0 ? 'amount-negative' : 'amount-positive' }}">{{ $compare ? rupiah($compareNet) : '-' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th data-label="Total" colspan="2">Total</th>
                        <th data-label="Total Debit" class="money amount-positive">{{ rupiah($rows->sum('total_debit')) }}</th>
                        <th data-label="Total Kredit" class="money amount-positive">{{ rupiah($rows->sum('total_credit')) }}</th>
                        <th colspan="{{ ($compareRows ?? collect())->isNotEmpty() ? 3 : 2 }}"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
