@extends('layouts.app', ['title' => 'Atur Akses: '.$role->label])

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>{{ $role->label }}</span>
        <span class="badge text-bg-light border">{{ $role->name }}</span>
    </div>
    <form method="post" action="{{ route('roles.update', $role) }}" class="p-4">
        @csrf
        @method('put')

        @if($role->name === 'super_admin')
            <div class="alert alert-info mb-0">Super Admin otomatis memiliki semua akses dan tidak perlu diatur.</div>
        @else
            <div class="row g-3">
                @foreach($catalog as $module => $items)
                    <div class="col-lg-6">
                        <div class="permission-panel">
                            <div class="permission-title">{{ $module }}</div>
                            @foreach($items as [$code, $label])
                                @php $record = $permissions[$code] ?? null; @endphp
                                @if($record)
                                    <label class="permission-item">
                                        <input type="checkbox" name="permissions[]" value="{{ $code }}" @checked(in_array($code, old('permissions', $selected), true))>
                                        <span>
                                            <strong>{{ $label }}</strong>
                                            <small>{{ $code }}</small>
                                        </span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 d-flex justify-content-end gap-2">
                <a href="{{ route('roles.index') }}" class="btn btn-light">Batal</a>
                <button class="btn btn-primary"><span data-icon="save" class="app-icon me-1" aria-hidden="true"></span>Simpan Hak Akses</button>
            </div>
        @endif
    </form>
</div>
@endsection
