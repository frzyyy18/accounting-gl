@extends('layouts.app', ['title' => 'Daftar Akun / Chart of Account'])

@section('content')
<div class="card p-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
        <form class="row g-2 align-items-end flex-grow-1">
            <div class="col-md-3"><label class="form-label small mb-1">Cari</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Kode atau nama akun"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Tipe</label><select name="type" class="form-select"><option value="">Semua Tipe</option>@foreach($types as $key => $label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Kategori Pajak</label><select name="tax_category" class="form-select"><option value="">Semua Kategori</option><option value="none" @selected(request('tax_category')==='none')>Bukan Akun Pajak</option>@foreach($taxCategories as $key => $label)<option value="{{ $key }}" @selected(request('tax_category')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <div><a class="btn btn-outline-secondary btn-sm" href="{{ route('accounts.export') }}">Export Excel</a> <a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm">Tambah Akun</a></div>
    </div>
    <table class="table table-hover responsive-table">
        <thead><tr><th>@include('partials.sort-link', ['field' => 'code', 'label' => 'Kode'])</th><th>@include('partials.sort-link', ['field' => 'name', 'label' => 'Nama Akun'])</th><th>@include('partials.sort-link', ['field' => 'type', 'label' => 'Tipe'])</th><th>@include('partials.sort-link', ['field' => 'parent', 'label' => 'Parent'])</th><th>@include('partials.sort-link', ['field' => 'tax_category', 'label' => 'Kategori Pajak'])</th><th>@include('partials.sort-link', ['field' => 'status', 'label' => 'Status'])</th><th>Aksi</th></tr></thead>
        <tbody>
        @foreach($accounts as $account)
            @php $depth = $accountDepths[$account->id] ?? 0; @endphp
            <tr>
                <td data-label="Kode">{{ $account->code }}</td>
                <td data-label="Nama Akun">
                    <span class="d-inline-flex align-items-center gap-1" style="padding-left: {{ $depth * 22 }}px">
                        @if($depth > 0)<span class="text-muted">└</span>@endif
                        <span class="{{ $account->parent_id ? '' : 'fw-semibold' }}">{{ $account->name }}</span>
                    </span>
                </td>
                <td data-label="Tipe">{{ \App\Models\Account::TYPES[$account->type] }}</td>
                <td data-label="Parent">{{ $account->parent?->code }}</td>
                <td data-label="Kategori Pajak">{{ \App\Models\Account::TAX_CATEGORIES[$account->tax_category] ?? '-' }}</td>
                <td data-label="Status">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                <td data-label="Aksi" class="text-end">
                    <div class="d-inline-flex gap-1">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounts.edit',$account) }}">Edit</a>
                        @if(auth()->user()->canManage('account.manage'))
                            <form method="post" action="{{ route('accounts.destroy', $account) }}">
                                @csrf @method('delete')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><span data-icon="trash" class="app-icon me-1" aria-hidden="true"></span>Hapus</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $accounts->links() }}
</div>
@endsection
