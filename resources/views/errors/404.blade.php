@extends('layouts.app', ['title' => 'Halaman Tidak Ditemukan'])

@section('content')
<div class="card p-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <div class="text-muted small mb-1">404</div>
            <h5 class="mb-2">Halaman Tidak Ditemukan</h5>
            <p class="text-muted mb-0">Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
</div>
@endsection
