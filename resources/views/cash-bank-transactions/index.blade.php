@extends('layouts.app', ['title' => $selectedKind === 'cash' ? 'Mutasi Kas' : ($selectedKind === 'bank' ? 'Mutasi Bank' : 'Mutasi Kas/Bank')])

@section('content')
<div class="card p-3">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-2 mb-3">
        <div class="btn-group">
            <a href="{{ route('cash-bank-transactions.index', ['kind' => 'cash']) }}" class="btn btn-sm {{ $selectedKind === 'cash' ? 'btn-primary' : 'btn-outline-primary' }}">Mutasi Kas</a>
            <a href="{{ route('cash-bank-transactions.index', ['kind' => 'bank']) }}" class="btn btn-sm {{ $selectedKind === 'bank' ? 'btn-primary' : 'btn-outline-primary' }}">Mutasi Bank</a>
            <a href="{{ route('cash-bank-transactions.index') }}" class="btn btn-sm {{ ! $selectedKind ? 'btn-primary' : 'btn-outline-primary' }}">Semua</a>
        </div>
        @if(auth()->user()->canManage('journal.create'))
            <a href="{{ route('journals.create') }}" class="btn btn-primary btn-sm">
                <span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Jurnal
            </a>
        @endif
    </div>

    <form class="row g-2 mb-3">
        <input type="hidden" name="kind" value="{{ $selectedKind }}">
        <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-2"><select name="branch_id" class="form-select"><option value="">Semua Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="cash_bank_id" class="form-select" data-searchable><option value="">Semua {{ $selectedKind === 'bank' ? 'Bank' : ($selectedKind === 'cash' ? 'Kas' : 'Kas/Bank') }}</option>@foreach($cashBanks as $cashBank)<option value="{{ $cashBank->id }}" @selected($selectedCashBankId==$cashBank->id)>{{ $cashBank->name }} - {{ $cashBank->account?->code }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="sort_direction" class="form-select"><option value="newest" @selected($sortDirection === 'newest')>Terbaru dulu</option><option value="oldest" @selected($sortDirection === 'oldest')>Terlama dulu</option></select></div>
        <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Filter</button></div>
    </form>

    <table class="table table-hover responsive-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Voucher Nomor</th>
                <th>Kas/Bank</th>
                <th>Deskripsi</th>
                <th>Lawan</th>
                <th class="money">Debit</th>
                <th class="money">Credit</th>
                <th class="money">Balance Total</th>
                <th>Jurnal</th>
            </tr>
        </thead>
        <tbody>
        @forelse($mutationRows as $row)
            <tr>
                <td data-label="Tanggal">{{ $row['date']->format('d/m/Y') }}</td>
                <td data-label="Voucher Nomor">{{ $row['reference'] }}</td>
                <td data-label="Kas/Bank">{{ $row['cash_bank']->name }}</td>
                <td data-label="Deskripsi">{{ $row['description'] }}</td>
                <td data-label="Lawan">{{ $row['opposite'] }}</td>
                <td data-label="Debit" class="money">{{ $row['debit'] > 0 ? rupiah($row['debit']) : '-' }}</td>
                <td data-label="Credit" class="money">{{ $row['credit'] > 0 ? rupiah($row['credit']) : '-' }}</td>
                <td data-label="Balance Total" class="money">{{ rupiah($row['balance']) }}</td>
                <td data-label="Jurnal">@if($row['journal'])<a href="{{ route('journals.show',$row['journal']) }}">{{ $row['journal']->reference_number ?: $row['journal']->journal_number }}</a>@else - @endif</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted">Belum ada mutasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
