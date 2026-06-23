<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'address', 'phone', 'email',
        'manager_name', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cashBanks()
    {
        return $this->hasMany(CashBank::class);
    }

    public function journals()
    {
        return $this->hasMany(JournalEntry::class);
    }
}
