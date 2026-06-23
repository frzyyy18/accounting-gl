@extends('layouts.app', ['title' => 'Buat Rekonsiliasi Bank'])

@section('content')
<div class="card p-4 mb-3">
    <form method="get" action="{{ route('bank-reconciliations.create') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-6"><label class="form-label">Pilih Bank</label><select name="cash_bank_id" class="form-select" required><option value="">Pilih Bank</option>@foreach($cashBanks as $bank)<option value="{{ $bank->id }}" @selected(request('cash_bank_id')==$bank->id)>{{ $bank->name }} - {{ $bank->branch?->code }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Tanggal Rekening Koran</label><input type="date" name="statement_date" class="form-control" value="{{ $statementDate ?? date('Y-m-d') }}" required></div>
            <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Tampilkan Mutasi</button></div>
        </div>
    </form>
</div>
@if($selected)
<div class="card p-4">
    <h5>{{ $selected->name }}</h5>
    <form method="post" action="{{ route('bank-reconciliations.store') }}">
        @csrf
        <input type="hidden" name="cash_bank_id" value="{{ $selected->id }}">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><label class="form-label">Tanggal Rekening Koran</label><input type="date" name="statement_date" class="form-control" value="{{ $statementDate ?? date('Y-m-d') }}" required></div>
            <div class="col-md-4"><label class="form-label">Saldo Menurut Bank</label><input type="number" step="0.01" name="bank_statement_balance" class="form-control money" required></div>
            <div class="col-md-4"><label class="form-label">Catatan</label><input name="notes" class="form-control"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">{{ $transactions->count() }} mutasi belum rekonsiliasi</div>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-check-all>Centang Semua</button>
        </div>
        <table class="table table-sm table-bordered responsive-table">
            <thead><tr><th></th><th>Tanggal</th><th>Tipe</th><th>Referensi</th><th>Keterangan</th><th class="money">Nominal</th><th class="money">Saldo Berjalan</th></tr></thead>
            <tbody>@forelse($transactions as $transaction)@php $nominal = $transaction['debit'] > 0 ? $transaction['debit'] : $transaction['credit']; @endphp<tr><td data-label="Pilih"><input type="checkbox" name="movement_keys[]" value="{{ $transaction['source_key'] }}" checked data-reconciliation-checkbox></td><td data-label="Tanggal">{{ $transaction['date']->format('d/m/Y') }}</td><td data-label="Tipe">{{ $transaction['movement_type'] === 'manual_in' || $transaction['movement_type'] === 'manual_out' ? 'Jurnal Manual' : 'Transaksi Kas/Bank' }}</td><td data-label="Referensi">{{ $transaction['reference'] }}</td><td data-label="Keterangan">{{ $transaction['description'] }}</td><td data-label="Nominal" class="money">{{ rupiah($nominal) }}</td><td data-label="Saldo Berjalan" class="money">{{ rupiah($transaction['balance'] ?? 0) }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">Tidak ada mutasi belum rekonsiliasi.</td></tr>@endforelse</tbody>
        </table>
        <button class="btn btn-primary">Simpan Rekonsiliasi</button>
    </form>
</div>
@endif
@endsection

@push('scripts')
<script>
document.querySelector('[data-check-all]')?.addEventListener('click', event => {
    const boxes = [...document.querySelectorAll('[data-reconciliation-checkbox]')];
    const shouldCheck = boxes.some(box => !box.checked);
    boxes.forEach(box => box.checked = shouldCheck);
    event.currentTarget.textContent = shouldCheck ? 'Kosongkan Semua' : 'Centang Semua';
});
</script>
@endpush
