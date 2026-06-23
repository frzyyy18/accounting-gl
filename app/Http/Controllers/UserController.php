<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role', 'company')
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->latest()
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->roleOptions(),
            'companies' => $this->companyOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLog::record('create', 'User', null, $user->only(['id', 'name', 'email', 'role_id', 'company_id']));

        return redirect()->route('users.index')->with('success', 'User berhasil dibuat.');
    }

    public function show(User $user)
    {
        $this->authorizeUser($user);

        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user)
    {
        $this->authorizeUser($user);

        return view('users.form', [
            'user' => $user,
            'roles' => $this->roleOptions(),
            'companies' => $this->companyOptions(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUser($user);

        $old = $user->only(['id', 'name', 'email', 'role_id', 'company_id', 'is_active']);
        $user->update($this->validated($request, $user));
        AuditLog::record('update', 'User', $old, $user->fresh()->only(['id', 'name', 'email', 'role_id', 'company_id', 'is_active']));

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Hanya Super Admin yang boleh menghapus user.');
        abort_if($user->id === auth()->id(), 422, 'Tidak boleh menghapus user sendiri.');
        $old = $user->only(['id', 'name', 'email']);
        $user->delete();
        AuditLog::record('delete', 'User', $old);

        return back()->with('success', 'User berhasil dihapus.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $requestedRole = Role::find($request->input('role_id'));
        if ($requestedRole && ! auth()->user()->hasRole('super_admin') && $requestedRole->name === 'super_admin') {
            abort(403, 'Admin perusahaan tidak boleh membuat atau mengubah Super Admin.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'role_id' => ['required', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $rules['password'] = $user ? ['nullable', 'confirmed', Password::defaults()] : ['required', 'confirmed', Password::defaults()];
        $data = $request->validate($rules, [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan user lain.',
            'role_id.required' => 'Role wajib dipilih.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password harus sama dengan password.',
            'password.min' => 'Password minimal 8 karakter.',
        ], [
            'name' => 'nama',
            'email' => 'email',
            'role_id' => 'role',
            'company_id' => 'perusahaan',
            'password' => 'password',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $role = Role::findOrFail($data['role_id']);

        $data['role_id'] = $role->id;

        if (auth()->user()->hasRole('super_admin')) {
            if (! empty($data['company_id'])) {
                $data['company_id'] = Company::findOrFail($data['company_id'])->id;
            } elseif ($role->name !== 'super_admin') {
                $data['company_id'] = Company::orderBy('name')->value('id');
            }
        } else {
            $data['company_id'] = auth()->user()->company_id;
        }

        return $data;
    }

    private function authorizeUser(User $user): void
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $user->company_id, 403);
    }

    private function roleOptions()
    {
        return Role::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->orderBy('label')
            ->get();
    }

    private function companyOptions()
    {
        return Company::query()
            ->when(! auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }
}
