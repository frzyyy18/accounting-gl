@extends('layouts.app', ['title' => 'Audit Trail'])

@section('content')
<div class="card p-3">
    <h5>Audit Trail</h5>
    <form class="row g-2 align-items-end mb-3 no-print">
        <div class="col-md-2"><label class="form-label small mb-1">Urutan</label><select name="sort_direction" class="form-select"><option value="newest" @selected(($sortDirection ?? request('sort_direction', 'newest')) === 'newest')>Terbaru dulu</option><option value="oldest" @selected(($sortDirection ?? request('sort_direction')) === 'oldest')>Terlama dulu</option></select></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Filter</button></div>
    </form>
    <table class="table table-hover table-sm">
        <thead><tr><th>Waktu</th><th>User</th><th>Module</th><th>Action</th><th>IP Address</th><th>User Agent</th></tr></thead>
        <tbody>@foreach($logs as $log)<tr><td>{{ $log->created_at->format('d/m/Y H:i') }}</td><td>{{ $log->user?->name }}</td><td>{{ $log->module }}</td><td>{{ $log->action }}</td><td>{{ $log->ip_address }}</td><td class="text-truncate" style="max-width:300px">{{ $log->user_agent }}</td></tr>@endforeach</tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection
