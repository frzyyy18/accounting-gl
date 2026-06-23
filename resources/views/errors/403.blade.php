@extends('layouts.app', ['title' => 'Akses Ditolak'])

@section('content')
<div class="card p-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <div class="text-muted small mb-1">403</div>
            <h5 class="mb-2">Akses Ditolak</h5>
            <p class="text-muted mb-0">{{ $exception->getMessage() ?: 'Anda tidak memiliki hak akses untuk membuka halaman ini.' }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
</div>
@endsection
