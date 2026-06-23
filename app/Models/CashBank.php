<?php

namespace App\Models;

use App\Support\CashBankMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBank extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'scope', 'kind', 'account_id', 'name', 'account_number',
        'bank_name', 'opening_balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions()
    {
        return $this->hasMany(CashBankTransaction::class);
    }

    public function targetTransactions()
    {
        return $this->hasMany(CashBankTransaction::class, 'target_cash_bank_id');
    }

    public function currentBalance(): float
    {
        return CashBankMutation::currentBalance($this);
    }
}
