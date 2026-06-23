@extends('layouts.app', ['title' => 'Closing Entry'])

@section('content')
<div class="card p-4">
    <h5>Closing Entry</h5>
    <p class="text-muted">Menutup saldo akun pendapatan dan beban ke akun ekuitas pada akhir periode. Sistem membuat jurnal posted otomatis dan menutup periode.</p>
    <form method="get" action="{{ route('closing-entries.create') }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Periode</label><select name="fiscal_period_id" class="form-select" required><option value="">Pilih Periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(request('fiscal_period_id')==$period->id)>{{ $period->name }} ({{ $period->start_date->format('d/m/Y') }} - {{ $period->end_date->format('d/m/Y') }}) - {{ strtoupper($period->status) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Akun Ekuitas Tujuan</label><select name="equity_account_id" class="form-select" required><option value="">Pilih Akun Ekuitas</option>@foreach($equityAccounts as $account)<option value="{{ $account->id }}" @selected(request('equity_account_id')==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Deskripsi</label><input name="description" class="form-control" placeholder="Closing Entry akhir periode"></div>
        </div>
        <div class="mt-4"><button class="btn btn-outline-secondary">Preview Closing</button></div>
    </form>
</div>
@if($preview)
<div class="card p-4">
    <h5>Preview Closing Entry</h5>
    <div class="small text-muted mb-3">{{ $selectedPeriod->name }} ditutup ke {{ $selectedEquity->code }} - {{ $selectedEquity->name }}</div>
    <table class="table table-bordered table-sm responsive-table">
        <thead><tr><th>Akun</th><th>Tipe</th><th class="money">Debit Closing</th><th class="money">Kredit Closing</th></tr></thead>
        <tbody>
        @forelse($preview['rows'] as $row)
            <tr><td>{{ $row->code }} - {{ $row->name }}</td><td>{{ \App\Models\Account::TYPES[$row->type] ?? $row->type }}</td><td class="money">{{ $row->debit > 0 ? rupiah($row->debit) : '-' }}</td><td class="money">{{ $row->credit > 0 ? rupiah($row->credit) : '-' }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">Tidak ada saldo yang perlu ditutup.</td></tr>
        @endforelse
            <tr class="fw-semibold"><td>{{ $preview['equityRow']->code }} - {{ $preview['equityRow']->name }}</td><td>Ekuitas tujuan</td><td class="money">{{ $preview['equityRow']->debit > 0 ? rupiah($preview['equityRow']->debit) : '-' }}</td><td class="money">{{ $preview['equityRow']->credit > 0 ? rupiah($preview['equityRow']->credit) : '-' }}</td></tr>
        </tbody>
    </table>
    <form method="post" action="{{ route('closing-entries.store') }}">
        @csrf
        <input type="hidden" name="fiscal_period_id" value="{{ $selectedPeriod->id }}">
        <input type="hidden" name="equity_account_id" value="{{ $selectedEquity->id }}">
        <input type="hidden" name="description" value="{{ request('description') }}">
        <input type="hidden" name="confirm_preview" value="1">
        <button class="btn btn-primary" @disabled(empty($preview['rows']) || round($preview['netIncome'], 2) == 0.0)>Eksekusi Closing Entry</button>
    </form>
</div>
@endif
@endsection
