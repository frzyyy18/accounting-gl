@extends('layouts.app', ['title' => 'Multi-Factor Authentication'])

@section('content')
<div class="card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
        <div>
            <h5 class="mb-1">Multi-Factor Authentication</h5>
            <div class="text-muted">Status: {{ $enabled ? 'Aktif' : 'Belum aktif' }}</div>
        </div>
        <span class="badge {{ $enabled ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $enabled ? 'Aktif' : 'Nonaktif' }}</span>
    </div>

    @unless($enabled)
        <div class="row g-4 align-items-center">
            <div class="col-md-auto">
                @if($qrCode)
                    <img src="{{ $qrCode }}" alt="QR Code MFA" width="200" height="200">
                @else
                    <div class="border rounded d-flex align-items-center justify-content-center text-center text-muted p-3" style="width: 200px; height: 200px">
                        QR Code tidak tersedia
                    </div>
                @endif
            </div>
            <div class="col">
                <div class="mb-3">
                    <label class="form-label">Secret Key</label>
                    <input class="form-control" value="{{ $secret }}" readonly>
                </div>
                <form method="post" action="{{ route('mfa.enable') }}" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="code" class="form-control" style="max-width: 180px" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                    <button class="btn btn-primary">Aktifkan MFA</button>
                </form>
            </div>
        </div>
    @else
        <form method="post" action="{{ route('mfa.disable') }}" class="d-flex gap-2 flex-wrap">
            @csrf
            <input type="text" name="code" class="form-control" style="max-width: 180px" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn btn-outline-danger">Nonaktifkan MFA</button>
        </form>
    @endunless
</div>
@endsection
