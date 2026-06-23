@extends('layouts.app', ['title' => $account->exists ? 'Edit Akun' : 'Tambah Akun'])

@section('content')
<div class="card p-4">
    <form method="post" action="{{ $account->exists ? route('accounts.update',$account) : route('accounts.store') }}">
        @csrf @if($account->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Kode Akun</label><input name="code" class="form-control" value="{{ old('code',$account->code) }}" required></div>
            <div class="col-md-5"><label class="form-label">Nama Akun</label><input name="name" class="form-control" value="{{ old('name',$account->name) }}" required></div>
            <div class="col-md-4"><label class="form-label">Tipe Akun</label><select name="type" class="form-select" required>@foreach($types as $key=>$label)<option value="{{ $key }}" @selected(old('type',$account->type)===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Parent Account</label><select name="parent_id" class="form-select"><option value="">Tanpa Parent</option>@foreach($accounts as $row)<option value="{{ $row->id }}" @selected(old('parent_id',$account->parent_id)==$row->id)>{{ $row->code }} - {{ $row->name }}</option>@endforeach</select></div>
            <div class="col-md-6 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active',$account->is_active ?? true))> Aktif</label></div>
            <div class="col-md-4"><label class="form-label">% Dapat Dikurangkan Fiskal</label><input type="number" name="fiscal_deductibility" class="form-control" min="0" max="100" step="0.01" value="{{ old('fiscal_deductibility', $account->fiscal_deductibility ?? 100) }}"></div>
            <div class="col-md-8 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="is_non_deductible" value="1" class="form-check-input" @checked(old('is_non_deductible',$account->is_non_deductible ?? false))> Tidak Dapat Dikurangkan (Non-Deductible)</label></div>
            <div class="col-md-6">
                <label class="form-label">Kategori Pajak</label>
                <select name="tax_category" class="form-select">
                    <option value="">Bukan Akun Pajak</option>
                    @foreach(App\Models\Account::TAX_CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('tax_category', $account->tax_category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Isi hanya jika akun ini adalah akun hutang/tagihan pajak.</div>
            </div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Simpan</button><a href="{{ route('accounts.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
