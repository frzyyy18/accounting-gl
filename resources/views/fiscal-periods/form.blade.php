@extends('layouts.app', ['title' => $period->exists ? 'Edit Periode Akuntansi' : 'Tambah Periode Akuntansi'])

@section('content')
<div class="card p-4">
    <form method="post" action="{{ $period->exists ? route('fiscal-periods.update',$period) : route('fiscal-periods.store') }}">
        @csrf @if($period->exists) @method('put') @endif
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Nama Periode</label><input name="name" class="form-control" value="{{ old('name',$period->name) }}" required></div>
            <div class="col-md-3"><label class="form-label">Tanggal Mulai</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($period->start_date)->format('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label class="form-label">Tanggal Selesai</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($period->end_date)->format('Y-m-d')) }}" required></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['open'=>'Open','locked'=>'Locked','closed'=>'Closed'] as $key=>$label)<option value="{{ $key }}" @selected(old('status',$period->status)===$key)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Simpan</button><a href="{{ route('fiscal-periods.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
