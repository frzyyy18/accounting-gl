@extends('layouts.app', ['title' => 'User'])

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Manajemen User</span>
        <div class="d-flex gap-2">
            @if(auth()->user()->canManage('role.manage'))
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('roles.index') }}"><span data-icon="role" class="app-icon me-1" aria-hidden="true"></span>Role & Permission</a>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah User</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover responsive-table">
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Perusahaan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td data-label="Nama" class="fw-semibold">{{ $user->name }}</td>
                    <td data-label="Email">{{ $user->email }}</td>
                    <td data-label="Role">{{ $user->role?->label }}</td>
                    <td data-label="Perusahaan">{{ $user->company?->name }}</td>
                    <td data-label="Status"><span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td data-label="Aksi" class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('users.edit',$user) }}">Edit</a>
                            @if(auth()->user()->hasRole('super_admin') && $user->id !== auth()->id())
                                <form method="post" action="{{ route('users.destroy', $user) }}">
                                    @csrf @method('delete')
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><span data-icon="trash" class="app-icon me-1" aria-hidden="true"></span>Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $users->links() }}</div>
</div>
@endsection
