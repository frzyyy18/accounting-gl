<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['role_id', 'company_id', 'name', 'email', 'password', 'is_active', 'google2fa_secret', 'google2fa_enabled'];

    protected $hidden = ['password', 'remember_token'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return in_array($this->role?->name, $roles, true);
    }

    public function canManage(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return (bool) $this->role?->hasPermission($permission);
    }

    public function isTaxDivision(): bool
    {
        return $this->hasRole(['manager_pajak', 'admin_pajak']);
    }

    public function isInternalDivision(): bool
    {
        return $this->hasRole(['manager_internal', 'omm', 'kasir_cabang']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'google2fa_secret' => 'encrypted',
            'google2fa_enabled' => 'boolean',
        ];
    }
}
