@extends('layouts.app', ['title' => 'Tagihan Toko'])

@section('content')
<div class="card p-4">
    <div class="alert alert-light border d-flex gap-2 align-items-start">
        <span data-icon="info" class="app-icon mt-1" aria-hidden="true"></span>
        <div>
            <div class="fw-semibold">Tagihan toko membentuk Piutang Dagang dan Penjualan.</div>
            <div class="small text-muted">Gunakan ini sebelum mencatat pembayaran toko lewat Kas Masuk atau Bank Masuk.</div>
        </div>
    </div>
    <form method="post" action="{{ route('store-receivables.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Tanggal Tagihan</label><input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label class="form-label">Cabang</label><select name="branch_id" class="form-select" data-searchable required><option value="">Pilih Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Nama Toko</label><input name="store_name" class="form-control" value="{{ old('store_name') }}" required></div>
            <div class="col-md-3"><label class="form-label">Nomor Invoice/Referensi</label><input name="reference_number" class="form-control" value="{{ old('reference_number') }}"></div>
            <div class="col-md-4"><label class="form-label">Akun Piutang</label><select name="receivable_account_id" class="form-select" data-searchable required><option value="">Pilih Piutang</option>@foreach($receivableAccounts as $account)<option value="{{ $account->id }}" @selected(old('receivable_account_id')==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Akun Penjualan</label><select name="sales_account_id" class="form-select" data-searchable required><option value="">Pilih Penjualan</option>@foreach($salesAccounts as $account)<option value="{{ $account->id }}" @selected(old('sales_account_id')==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Nominal Tagihan</label><input type="text" inputmode="decimal" name="amount" class="form-control money" data-money-input value="{{ old('amount') }}" required></div>
            <div class="col-md-12"><label class="form-label">Keterangan</label><input name="description" class="form-control" value="{{ old('description') }}" placeholder="Contoh: Tagihan toko ABC periode Mei"></div>
        </div>
        <div class="journal-preview mt-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <strong>Jurnal Otomatis</strong>
                <span class="badge text-bg-light border">Posted saat disimpan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Akun</th><th class="money">Debit</th><th class="money">Kredit</th></tr></thead>
                    <tbody>
                        <tr><td>Piutang Dagang</td><td class="money">Nominal tagihan</td><td class="money">-</td></tr>
                        <tr><td>Penjualan</td><td class="money">-</td><td class="money">Nominal tagihan</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary"><span data-icon="save" class="app-icon me-1" aria-hidden="true"></span>Posting Tagihan</button>
            <a href="{{ route('journals.index') }}" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
@endsection
