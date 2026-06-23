<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'transaction_date', 'journal_number', 'reference_number',
        'description', 'attachment_path', 'attachment_name', 'status', 'created_by', 'submitted_by',
        'approved_by', 'cancelled_by', 'posted_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function details()
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDebit(): float
    {
        return (float) $this->details->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->details->sum('credit');
    }
}
