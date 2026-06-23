@extends('layouts.app', ['title' => $company->exists ? 'Edit Perusahaan' : 'Tambah Perusahaan'])

@section('content')
<div class="card p-4">
    <form method="post" action="{{ $company->exists ? route('companies.update',$company) : route('companies.store') }}">
        @csrf @if($company->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Kode Perusahaan</label><input name="code" class="form-control text-uppercase" maxlength="20" value="{{ old('code',$company->code) }}" placeholder="Contoh: ABC" required></div>
            <div class="col-md-3"><label class="form-label">Nama Perusahaan</label><input name="name" class="form-control" value="{{ old('name',$company->name) }}" required></div>
            <div class="col-md-3"><label class="form-label">NPWP</label><input name="tax_number" class="form-control" value="{{ old('tax_number',$company->tax_number) }}"></div>
            <div class="col-md-3"><label class="form-label">Tahun Buku</label><input name="fiscal_year" type="number" class="form-control" value="{{ old('fiscal_year',$company->fiscal_year ?: date('Y')) }}" required></div>
            <div class="col-md-4"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email',$company->email) }}"></div>
            <div class="col-md-4"><label class="form-label">Telepon</label><input name="phone" class="form-control" value="{{ old('phone',$company->phone) }}"></div>
            <div class="col-md-4"><label class="form-label">Mata Uang Utama</label><input name="base_currency" maxlength="3" class="form-control" value="{{ old('base_currency',$company->base_currency ?: 'IDR') }}" required></div>
            @if(auth()->user()->hasRole('super_admin'))
                <div class="col-md-4"><label class="form-label">Tarif PPh Badan (%)</label><input name="tax_rate_corporate" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('tax_rate_corporate', corporateTaxRate() * 100) }}"></div>
            @endif
            <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control">{{ old('address',$company->address) }}</textarea></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active',$company->is_active ?? true))> Aktif</label></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Simpan</button><a href="{{ route('companies.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
