@extends('layouts.app', ['title' => 'Pengaturan Sistem'])

@section('content')
<div class="card p-4">
    <h5>Pengaturan Sistem</h5>
    <form method="post" action="{{ route('system-settings.update') }}" class="row g-3 mt-1">
        @csrf
        @method('put')
        <div class="col-md-4">
            <label class="form-label">Tarif PPh Badan (%)</label>
            <input name="tax_rate_corporate" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('tax_rate_corporate', $taxRateCorporate) }}" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Simpan Pengaturan</button>
        </div>
    </form>
</div>
@endsection
