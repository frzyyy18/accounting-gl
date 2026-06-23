<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'address', 'tax_number', 'email', 'phone', 'logo_path',
        'fiscal_year', 'base_currency', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function cashBanks()
    {
        return $this->hasMany(CashBank::class);
    }

    public function fiscalPeriods()
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    public function journals()
    {
        return $this->hasMany(JournalEntry::class);
    }
}
