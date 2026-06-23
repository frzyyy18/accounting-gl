@extends('layouts.app', ['title' => $branch->exists ? 'Edit Cabang' : 'Tambah Cabang'])

@section('content')
<div class="card p-4">
    <form method="post" action="{{ $branch->exists ? route('branches.update',$branch) : route('branches.store') }}">
        @csrf @if($branch->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Perusahaan</label><select name="company_id" class="form-select" required @disabled(!auth()->user()->hasRole('super_admin'))>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id',$branch->company_id ?: auth()->user()->company_id)==$company->id)>{{ $company->code ? $company->code.' - ' : '' }}{{ $company->name }}</option>@endforeach</select>@unless(auth()->user()->hasRole('super_admin'))<input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">@endunless</div>
            <div class="col-md-3"><label class="form-label">Kode Cabang</label><input name="code" class="form-control text-uppercase" value="{{ old('code',$branch->code) }}" required></div>
            <div class="col-md-5"><label class="form-label">Nama Cabang</label><input name="name" class="form-control" value="{{ old('name',$branch->name) }}" required></div>
            <div class="col-md-4"><label class="form-label">Manager</label><input name="manager_name" class="form-control" value="{{ old('manager_name',$branch->manager_name) }}"></div>
            <div class="col-md-4"><label class="form-label">Telepon</label><input name="phone" class="form-control" value="{{ old('phone',$branch->phone) }}"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$branch->email) }}"></div>
            <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active',$branch->is_active ?? true))> Aktif</label></div>
            <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="3">{{ old('address',$branch->address) }}</textarea></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Simpan</button><a href="{{ route('branches.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
