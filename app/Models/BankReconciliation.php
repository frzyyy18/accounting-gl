<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankReconciliation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'cash_bank_id', 'created_by', 'statement_date',
        'bank_statement_balance', 'book_balance', 'difference', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'bank_statement_balance' => 'decimal:2',
            'book_balance' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function cashBank()
    {
        return $this->belongsTo(CashBank::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(CashBankTransaction::class);
    }

    public function journalDetails()
    {
        return $this->hasMany(JournalDetail::class);
    }
}
