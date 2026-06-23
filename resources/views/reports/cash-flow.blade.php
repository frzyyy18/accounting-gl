@extends('layouts.app', ['title' => 'Arus Kas / Cash Flow'])

@section('content')
<div class="card p-3">
    <h5>Arus Kas / Cash Flow</h5>
    @include('reports.partials.filters')
    <div class="row g-3 mb-3">
        @foreach([
            'Saldo Awal' => $openingBalance,
            'Kas Masuk' => $cashIn,
            'Kas Keluar' => $cashOut,
            'Transfer Masuk' => $transferIn,
            'Transfer Keluar' => $transferOut,
            'Saldo Akhir' => $endingBalance,
        ] as $label => $value)
            <div class="col-md-2"><div class="card p-3 h-100"><div class="text-muted small">{{ $label }}</div><div class="h6 mb-0 money">{{ rupiah($value) }}</div></div></div>
        @endforeach
    </div>
    <table class="table table-bordered table-sm responsive-table">
        <thead><tr><th>Tanggal</th><th>Cabang</th><th>Tipe</th><th>Referensi</th><th>Keterangan</th><th class="money">Masuk</th><th class="money">Keluar</th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            @php
                $typeLabels = [
                    'cash_in' => 'Kas Masuk',
                    'bank_in' => 'Bank Masuk',
                    'cash_out' => 'Kas Keluar',
                    'transfer_in' => 'Transfer Masuk',
                    'transfer_out' => 'Transfer Keluar',
                    'manual_in' => 'Jurnal Manual Masuk',
                    'manual_out' => 'Jurnal Manual Keluar',
                ];
            @endphp
            <tr>
                <td data-label="Tanggal">{{ $transaction['date']->format('d/m/Y') }}</td>
                <td data-label="Cabang">{{ $transaction['journal']?->branch?->code }}</td>
                <td data-label="Tipe">{{ $typeLabels[$transaction['movement_type']] ?? 'Mutasi Kas/Bank' }}</td>
                <td data-label="Referensi">@if($transaction['journal'])<a href="{{ route('journals.show',$transaction['journal']) }}">{{ $transaction['reference'] }}</a>@else {{ $transaction['reference'] }} @endif</td>
                <td data-label="Keterangan">{{ $transaction['description'] }}</td>
                <td data-label="Masuk" class="money">{{ $transaction['debit'] > 0 ? rupiah($transaction['debit']) : '-' }}</td>
                <td data-label="Keluar" class="money">{{ $transaction['credit'] > 0 ? rupiah($transaction['credit']) : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted">Belum ada mutasi kas/bank pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
