@extends('layouts.app', ['title' => 'Role & Permission'])

@section('content')
<div class="card">
    <div class="card-header">Daftar Role</div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Role</th><th>Kode</th><th>User</th><th>Permission</th><th></th></tr></thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td class="fw-semibold">{{ $role->label }}</td>
                    <td><code>{{ $role->name }}</code></td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ $role->name === 'super_admin' ? 'Semua akses' : $role->permissionRecords->count().' permission' }}</td>
                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('roles.edit', $role) }}">Atur Akses</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
