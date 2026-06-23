@extends('layouts.app', ['title' => 'Laba Rugi / Profit & Loss'])

@section('content')
<div class="card p-3">
    <h5>Laba Rugi / Profit & Loss</h5>
    @include('reports.partials.filters')
    <table class="table table-bordered table-sm responsive-table">
        <thead><tr><th>Kode Akun</th><th>Nama Akun</th><th>Tipe</th><th class="money">Nilai</th>@if(($compareRows ?? collect())->isNotEmpty())<th class="money">Pembanding</th><th class="money">Selisih</th>@endif</tr></thead>
        <tbody>
        @foreach($rows as $row)
            @php
                $value = in_array($row->type, ['revenue','other_income']) ? $row->total_credit - $row->total_debit : $row->total_debit - $row->total_credit;
                $compare = ($compareRows ?? collect())->get($row->id);
                $compareValue = $compare ? (in_array($row->type, ['revenue','other_income']) ? $compare->total_credit - $compare->total_debit : $compare->total_debit - $compare->total_credit) : null;
            @endphp
            <tr><td data-label="Kode Akun">{{ $row->code }}</td><td data-label="Nama Akun"><a href="{{ route('reports.ledger', request()->except(['account_id','export']) + ['account_id' => $row->id]) }}">{{ $row->name }}</a></td><td data-label="Tipe">{{ \App\Models\Account::TYPES[$row->type] }}</td><td data-label="Nilai" class="money">{{ rupiah($value) }}</td>@if(($compareRows ?? collect())->isNotEmpty())<td data-label="Pembanding" class="money">{{ $compare ? rupiah($compareValue) : '-' }}</td><td data-label="Selisih" class="money">{{ $compare ? rupiah($value - $compareValue) : '-' }}</td>@endif</tr>
        @endforeach
        </tbody>
        <tfoot><tr><th data-label="Ringkasan" colspan="3">Total Pendapatan</th><th data-label="Nilai" class="money">{{ rupiah($income) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th colspan="2"></th>@endif</tr><tr><th data-label="Ringkasan" colspan="3">Total Beban</th><th data-label="Nilai" class="money">{{ rupiah($expense) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th colspan="2"></th>@endif</tr><tr><th data-label="Ringkasan" colspan="3">Laba/Rugi Bersih</th><th data-label="Nilai" class="money">{{ rupiah($income - $expense) }}</th>@if(($compareRows ?? collect())->isNotEmpty())<th colspan="2"></th>@endif</tr></tfoot>
    </table>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
