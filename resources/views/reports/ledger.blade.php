@extends('layouts.app', ['title' => 'Buku Besar / General Ledger'])

@section('content')
<div class="card p-3">
    <h5>Buku Besar / General Ledger</h5>
    @include('reports.partials.filters')
    <table class="table table-bordered table-sm responsive-table">
        <thead><tr><th>Tanggal</th><th>Nomor Jurnal</th><th>Referensi</th><th>Akun</th><th>Keterangan</th><th class="money">Debit</th><th class="money">Kredit</th><th class="money">Saldo Berjalan</th></tr></thead>
        <tbody>
        @foreach($details as $detail)
            <tr><td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($detail->transaction_date)->format('d/m/Y') }}</td><td data-label="Nomor Jurnal"><a href="{{ route('journals.show',$detail->journal_entry_id) }}">{{ $detail->journal_number }}</a></td><td data-label="Referensi">{{ $detail->reference_number }}</td><td data-label="Akun">{{ $detail->code }} - {{ $detail->name }}</td><td data-label="Keterangan">{{ $detail->description }}</td><td data-label="Debit" class="money">{{ rupiah($detail->debit) }}</td><td data-label="Kredit" class="money">{{ rupiah($detail->credit) }}</td><td data-label="Saldo Berjalan" class="money">{{ rupiah($detail->running_balance ?? 0) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
