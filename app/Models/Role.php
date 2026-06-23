<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissionRecords()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->name === 'super_admin') {
            return true;
        }

        if ($this->relationLoaded('permissionRecords')) {
            return $this->permissionRecords->contains('code', $permission);
        }

        if ($this->permissionRecords()->where('code', $permission)->exists()) {
            return true;
        }

        $default = self::defaultPermissions()[$this->name] ?? [];
        if (empty($this->permissions) && in_array($permission, $default, true)) {
            return true;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    public static function defaultPermissions(): array
    {
        $all = collect(Permission::catalog())->flatten(1)->pluck(0)->all();

        return [
            'super_admin' => $all,
            'manager_pajak' => ['dashboard.view', 'company.manage', 'branch.manage', 'account.view', 'journal.view', 'journal.create', 'journal.approve', 'cash_bank.view', 'cash_transaction.view', 'cash_transaction.create', 'report.view', 'tax_report.view', 'audit_trail.view'],
            'admin_pajak' => ['dashboard.view', 'company.manage', 'branch.manage', 'account.view', 'journal.view', 'journal.create', 'cash_bank.view', 'cash_transaction.view', 'cash_transaction.create', 'report.view', 'tax_report.view'],
            'manager_internal' => ['dashboard.view', 'branch.manage', 'period.manage', 'account.view', 'account.manage', 'journal.view', 'journal.create', 'journal.approve', 'journal.post', 'journal.cancel', 'journal.delete', 'cash_bank.view', 'cash_bank.manage', 'cash_transaction.view', 'cash_transaction.create', 'bank_reconciliation.view', 'bank_reconciliation.create', 'closing.create', 'report.view', 'user.manage'],
            'omm' => ['dashboard.view', 'branch.manage', 'period.manage', 'account.view', 'account.manage', 'journal.view', 'journal.create', 'journal.approve', 'journal.post', 'journal.cancel', 'journal.delete', 'cash_bank.view', 'cash_bank.manage', 'cash_transaction.view', 'cash_transaction.create', 'bank_reconciliation.view', 'bank_reconciliation.create', 'closing.create', 'report.view', 'user.manage'],
            'kasir_cabang' => ['dashboard.view', 'account.view', 'cash_bank.view', 'cash_transaction.view', 'cash_transaction.create', 'report.view'],
            'auditor' => ['dashboard.view', 'account.view', 'journal.view', 'cash_bank.view', 'cash_transaction.view', 'bank_reconciliation.view', 'report.view', 'audit_trail.view'],
        ];
    }
}
