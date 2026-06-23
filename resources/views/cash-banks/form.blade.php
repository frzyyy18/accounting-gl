@extends('layouts.app', ['title' => $cashBank->exists ? 'Edit Kas/Bank' : 'Tambah Kas/Bank'])

@section('content')
<div class="card p-4">
    <form method="post" action="{{ $cashBank->exists ? route('cash-banks.update',$cashBank) : route('cash-banks.store') }}">
        @csrf @if($cashBank->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Nama Kas/Bank</label><input name="name" class="form-control" value="{{ old('name',$cashBank->name) }}" required></div>
            <div class="col-md-2"><label class="form-label">Jenis</label><select name="kind" class="form-select" required><option value="cash" @selected(old('kind',$cashBank->kind ?? 'cash')==='cash')>Kas</option><option value="bank" @selected(old('kind',$cashBank->kind)==='bank')>Bank</option></select></div>
            <div class="col-md-3"><label class="form-label">Scope</label><select name="scope" class="form-select" required><option value="company" @selected(old('scope',$cashBank->scope ?? 'company')==='company')>Perusahaan</option><option value="branch" @selected(old('scope',$cashBank->scope)==='branch')>Cabang</option></select></div>
            <div class="col-md-3"><label class="form-label">Cabang</label><select name="branch_id" class="form-select"><option value="">Tanpa Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id',$cashBank->branch_id)==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Akun GL</label><select name="account_id" class="form-select" required><option value="">Pilih Akun Aset</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('account_id',$cashBank->account_id)==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Saldo Awal</label><input type="number" step="0.01" min="0" name="opening_balance" class="form-control money" value="{{ old('opening_balance',$cashBank->opening_balance ?? 0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Nama Bank</label><input name="bank_name" class="form-control" value="{{ old('bank_name',$cashBank->bank_name) }}"></div>
            <div class="col-md-4"><label class="form-label">Nomor Rekening</label><input name="account_number" class="form-control" value="{{ old('account_number',$cashBank->account_number) }}"></div>
            <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active',$cashBank->is_active ?? true))> Aktif</label></div>
        </div>
        <div class="alert alert-light border small mt-3">Kas dapat memakai scope Perusahaan dan dipakai semua cabang. Bank otomatis diperlakukan sebagai scope Cabang dan wajib memilih cabang.</div>
        <div class="mt-4"><button class="btn btn-primary">Simpan</button><a href="{{ route('cash-banks.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
