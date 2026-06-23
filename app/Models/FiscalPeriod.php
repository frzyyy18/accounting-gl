<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->status === 'open') {
            return true;
        }

        return $user->hasRole(['super_admin', 'manager_internal', 'omm']);
    }
}
