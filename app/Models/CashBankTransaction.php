<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankTransaction extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'cash_in' => 'Kas Masuk',
        'bank_in' => 'Bank Masuk',
        'cash_out' => 'Kas Keluar',
        'transfer' => 'Transfer Bank',
    ];

    protected $fillable = [
        'company_id', 'cash_bank_id', 'target_cash_bank_id', 'counter_account_id',
        'journal_entry_id', 'branch_id', 'transaction_date', 'type', 'reference_number',
        'description', 'attachment_path', 'attachment_name', 'amount', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'is_reconciled' => 'boolean',
            'reconciled_at' => 'datetime',
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

    public function targetCashBank()
    {
        return $this->belongsTo(CashBank::class, 'target_cash_bank_id');
    }

    public function counterAccount()
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankReconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }
}
