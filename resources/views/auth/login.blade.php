@extends('layouts.app', ['title' => 'Login'])

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <section class="auth-brand-panel" aria-label="Accounting GL">
            <div class="auth-brand-top">
                <div class="login-logo">GL</div>
                <div>
                    <div class="auth-brand-name">Accounting GL</div>
                    <div class="auth-brand-subtitle">General Ledger System</div>
                </div>
            </div>

            <div class="auth-brand-copy">
                <div class="auth-kicker">Portal Keuangan</div>
                <h1>Kelola jurnal, laporan, dan audit dalam satu ruang kerja.</h1>
                <p>Masuk untuk melanjutkan pencatatan transaksi, rekonsiliasi, dan pemantauan periode akuntansi perusahaan.</p>
            </div>

            <div class="auth-feature-grid">
                <div class="auth-feature">
                    <span data-icon="shield" class="app-icon" aria-hidden="true"></span>
                    <span>MFA Ready</span>
                </div>
                <div class="auth-feature">
                    <span data-icon="graphUp" class="app-icon" aria-hidden="true"></span>
                    <span>Laporan Real-time</span>
                </div>
                <div class="auth-feature">
                    <span data-icon="lock" class="app-icon" aria-hidden="true"></span>
                    <span>Akses Terkontrol</span>
                </div>
            </div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-header">
                <div class="auth-status-pill">
                    <span data-icon="lock" class="app-icon" aria-hidden="true"></span>
                    <span>Secure sign in</span>
                </div>
                <h2>Masuk ke akun</h2>
                <p>Gunakan email dan password yang sudah terdaftar.</p>
            </div>

            <form method="post" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <div class="auth-input">
                        <span data-icon="user" class="app-icon" aria-hidden="true"></span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="nama@perusahaan.com" autocomplete="email" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input">
                        <span data-icon="lock" class="app-icon" aria-hidden="true"></span>
                        <input id="password" type="password" name="password" class="form-control" placeholder="Masukkan password" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                    <label class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remember" value="1">
                        <span class="form-check-label">Ingat saya</span>
                    </label>
                    <span class="auth-help-text">Akses sesuai role</span>
                </div>

                <button class="btn btn-primary w-100 auth-submit">
                    <span data-icon="lock" class="app-icon me-1" aria-hidden="true"></span>
                    Login
                </button>
            </form>
        </section>
    </div>
</div>
@endsection
