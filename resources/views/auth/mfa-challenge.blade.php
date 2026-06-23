@extends('layouts.app', ['title' => 'Verifikasi MFA'])

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card login-panel p-4">
        <div class="login-logo">GL</div>
        <h4 class="mb-1 fw-bold">Verifikasi MFA</h4>
        <p class="text-muted mb-4">Masukkan kode 6 digit dari aplikasi authenticator.</p>
        <form method="post" action="{{ route('mfa.verify') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Kode MFA</label>
                <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
            </div>
            <button class="btn btn-primary w-100 py-2">Verifikasi</button>
        </form>
    </div>
</div>
@endsection
