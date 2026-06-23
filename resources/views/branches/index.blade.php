@extends('layouts.app', ['title' => 'Cabang'])

@section('content')
<div class="card p-3">
    <div class="d-flex justify-content-between mb-3">
        <h5>Cabang</h5>
        <a href="{{ route('branches.create', ['company_id' => $selectedCompanyId]) }}" class="btn btn-primary btn-sm">Tambah Cabang</a>
    </div>
    @if(auth()->user()->hasRole('super_admin'))
        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">Perusahaan</label>
                <select name="company_id" class="form-select">
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected($selectedCompanyId==$company->id)>{{ $company->code ? $company->code.' - ' : '' }}{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-outline-secondary">Tampilkan</button></div>
        </form>
    @endif
    <table class="table table-hover">
        <thead><tr>@if(auth()->user()->hasRole('super_admin'))<th>Perusahaan</th>@endif<th>Kode</th><th>Nama Cabang</th><th>Manager</th><th>Telepon</th><th>Email</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($branches as $branch)
            <tr>
                @if(auth()->user()->hasRole('super_admin'))<td>{{ $branch->company?->code ?: $branch->company?->name }}</td>@endif
                <td>{{ $branch->code }}</td>
                <td>{{ $branch->name }}</td>
                <td>{{ $branch->manager_name }}</td>
                <td>{{ $branch->phone }}</td>
                <td>{{ $branch->email }}</td>
                <td>{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                <td class="text-end">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('branches.edit',$branch) }}">Edit</a>
                    <form method="post" action="{{ route('branches.destroy',$branch) }}" class="d-inline" onsubmit="return confirm('Hapus cabang ini? Jika belum ada transaksi, kas/bank cabang ini ikut dihapus.')">
                        @csrf @method('delete')
                        <button class="btn btn-outline-danger btn-sm">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ auth()->user()->hasRole('super_admin') ? 8 : 7 }}" class="text-center text-muted">Belum ada cabang.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $branches->links() }}
</div>
@endsection
