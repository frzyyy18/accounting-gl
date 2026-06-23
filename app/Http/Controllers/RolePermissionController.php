<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::withCount('users')->with('permissionRecords')->orderBy('label')->get(),
        ]);
    }

    public function edit(Role $role)
    {
        return view('roles.form', [
            'role' => $role->load('permissionRecords'),
            'catalog' => Permission::catalog(),
            'permissions' => Permission::orderBy('module')->orderBy('label')->get()->keyBy('code'),
            'selected' => $role->permissionRecords->pluck('code')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->name === 'super_admin', 422, 'Super Admin otomatis memiliki semua akses.');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $old = $role->load('permissionRecords')->permissionRecords->pluck('code')->values()->all();
        $permissionIds = Permission::whereIn('code', $data['permissions'] ?? [])->pluck('id')->all();
        $role->permissionRecords()->sync($permissionIds);
        $role->update(['permissions' => $data['permissions'] ?? []]);

        AuditLog::record('update_permission', 'Role & Permission', ['role' => $role->name, 'permissions' => $old], [
            'role' => $role->name,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')->with('success', 'Hak akses role berhasil diperbarui.');
    }
}
