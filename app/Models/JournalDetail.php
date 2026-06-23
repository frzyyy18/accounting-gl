<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $fillable = ['journal_entry_id', 'bank_reconciliation_id', 'account_id', 'description', 'debit', 'credit', 'fiscal_amount', 'fiscal_note', 'is_reconciled', 'reconciled_at'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'fiscal_amount' => 'decimal:2', 'is_reconciled' => 'boolean', 'reconciled_at' => 'datetime'];
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function bankReconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }
}
