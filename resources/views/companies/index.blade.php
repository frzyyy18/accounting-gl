@extends('layouts.app', ['title' => 'Profil Perusahaan'])

@section('content')
<div class="card p-3">
    <div class="d-flex justify-content-between mb-3"><h5>Profil Perusahaan</h5><a href="{{ route('companies.create') }}" class="btn btn-primary btn-sm">Tambah Perusahaan</a></div>
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>NPWP</th><th>Email</th><th>Tahun Buku</th><th>Mata Uang</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($companies as $company)
            <tr>
                <td>{{ $company->code }}</td><td>{{ $company->name }}</td><td>{{ $company->tax_number }}</td><td>{{ $company->email }}</td><td>{{ $company->fiscal_year }}</td><td>{{ $company->base_currency }}</td><td>{{ $company->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                <td class="text-end">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('branches.create', ['company_id' => $company->id]) }}">Tambah Cabang</a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('companies.edit',$company) }}">Edit</a>
                    <form method="post" action="{{ route('companies.destroy',$company) }}" class="d-inline" onsubmit="return confirm('Hapus perusahaan ini? Jika belum ada transaksi, cabang, akun, periode, dan kas/bank milik perusahaan ini ikut dihapus.')">
                        @csrf @method('delete')
                        <button class="btn btn-outline-danger btn-sm">Hapus</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $companies->links() }}
</div>
@endsection
