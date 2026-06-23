@extends('layouts.app', ['title' => 'Kas & Bank'])

@section('content')
<div class="card p-3">
    <div class="d-flex justify-content-between mb-3">
        <h5>Kas & Bank</h5>
        <a href="{{ route('cash-banks.create') }}" class="btn btn-primary btn-sm">Tambah Kas/Bank</a>
    </div>
    <table class="table table-hover">
        <thead><tr><th>Nama</th><th>Jenis</th><th>Scope</th><th>Cabang</th><th>Akun GL</th><th>Bank</th><th>No. Rekening</th><th class="money">Saldo Saat Ini</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($cashBanks as $cashBank)
            <tr>
                <td>{{ $cashBank->name }}</td>
                <td>{{ strtoupper($cashBank->kind ?? 'cash') }}</td>
                <td>{{ ($cashBank->scope ?? 'company') === 'company' ? 'Perusahaan' : 'Cabang' }}</td>
                <td>{{ $cashBank->branch?->name ?: '-' }}</td>
                <td>{{ $cashBank->account?->code }} - {{ $cashBank->account?->name }}</td>
                <td>{{ $cashBank->bank_name }}</td>
                <td>{{ $cashBank->account_number }}</td>
                <td class="money">{{ rupiah($cashBank->currentBalance()) }}</td>
                <td>{{ $cashBank->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                <td class="text-end">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('cash-banks.show',$cashBank) }}">Mutasi</a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('cash-banks.edit',$cashBank) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted">Belum ada kas/bank.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $cashBanks->links() }}
</div>
@endsection
