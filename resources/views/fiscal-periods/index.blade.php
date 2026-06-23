@extends('layouts.app', ['title' => 'Periode Akuntansi'])

@section('content')
<div class="card p-3">
    <div class="d-flex justify-content-between mb-3">
        <h5>Periode Akuntansi</h5>
        <a href="{{ route('fiscal-periods.create') }}" class="btn btn-primary btn-sm">Tambah Periode</a>
    </div>
    <table class="table table-hover">
        <thead><tr><th>Nama</th><th>Mulai</th><th>Selesai</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($periods as $period)
            <tr>
                <td>{{ $period->name }}</td>
                <td>{{ $period->start_date->format('d/m/Y') }}</td>
                <td>{{ $period->end_date->format('d/m/Y') }}</td>
                <td><span class="badge text-bg-{{ $period->status === 'open' ? 'success' : ($period->status === 'locked' ? 'warning' : 'secondary') }}">{{ strtoupper($period->status) }}</span></td>
                <td class="text-end">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('fiscal-periods.edit',$period) }}">Edit</a>
                    <form method="post" action="{{ route('fiscal-periods.lock',$period) }}" class="d-inline">@csrf <button class="btn btn-outline-warning btn-sm">Lock</button></form>
                    <form method="post" action="{{ route('fiscal-periods.unlock',$period) }}" class="d-inline">@csrf <button class="btn btn-outline-success btn-sm">Unlock</button></form>
                    <form method="post" action="{{ route('fiscal-periods.close',$period) }}" class="d-inline">@csrf <button class="btn btn-outline-secondary btn-sm">Close</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $periods->links() }}
</div>
@endsection
