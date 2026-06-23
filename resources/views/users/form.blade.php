@extends('layouts.app', ['title' => $user->exists ? 'Edit User' : 'Tambah User'])

@section('content')
<div class="card">
    <div class="card-header">{{ $user->exists ? 'Edit Data User' : 'Tambah User Baru' }}</div>
    <form method="post" action="{{ $user->exists ? route('users.update',$user) : route('users.store') }}" class="p-4">
        @csrf @if($user->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nama</label>
                <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name',$user->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email',$user->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id',$user->role_id)==$role->id)>{{ $role->label }}</option>@endforeach</select>
                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Perusahaan</label>
                <select name="company_id" class="form-select @error('company_id') is-invalid @enderror"><option value="">Semua Perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id',$user->company_id)==$company->id)>{{ $company->name }}</option>@endforeach</select>
                @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Password {{ $user->exists ? 'Baru' : '' }}</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                    <button class="btn btn-light toggle-password" type="button" data-target="password" title="Tampilkan password" aria-label="Tampilkan password"><span data-icon="eye" class="app-icon" aria-hidden="true"></span></button>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text">{{ $user->exists ? 'Kosongkan jika password tidak ingin diganti.' : 'Minimal 8 karakter.' }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Konfirmasi Password</label>
                <div class="input-group">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                    <button class="btn btn-light toggle-password" type="button" data-target="password_confirmation" title="Tampilkan konfirmasi password" aria-label="Tampilkan konfirmasi password"><span data-icon="eye" class="app-icon" aria-hidden="true"></span></button>
                </div>
                <div class="form-text">Isi sama persis dengan password.</div>
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active',$user->is_active ?? true))> Aktif
                </label>
            </div>
        </div>
        <div class="mt-4 d-flex justify-content-end gap-2"><a class="btn btn-light" href="{{ route('users.index') }}">Batal</a><button class="btn btn-primary"><span data-icon="save" class="app-icon me-1" aria-hidden="true"></span>Simpan</button></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('[data-icon]');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.dataset.icon = input.type === 'password' ? 'eye' : 'eyeOff';
        icon.removeAttribute('data-icon-mounted');
        icon.replaceChildren();
        window.renderIcons?.(button);
    });
});
</script>
@endpush
