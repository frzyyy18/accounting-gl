<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'asset' => 'Asset / Aset',
        'liability' => 'Liability / Kewajiban',
        'equity' => 'Equity / Ekuitas',
        'revenue' => 'Revenue / Pendapatan',
        'expense' => 'Expense / Beban',
        'other_income' => 'Other Income / Pendapatan Lain-lain',
        'other_expense' => 'Other Expense / Beban Lain-lain',
    ];

    public const TAX_CATEGORIES = [
        'ppn' => 'PPN',
        'pph21' => 'PPh 21',
        'pph23' => 'PPh 23',
        'pph_final' => 'PPh Final / PPh 4(2)',
    ];

    protected $fillable = ['company_id', 'parent_id', 'code', 'name', 'type', 'fiscal_deductibility', 'is_non_deductible', 'tax_category', 'is_active'];

    protected function casts(): array
    {
        return ['fiscal_deductibility' => 'decimal:2', 'is_non_deductible' => 'boolean', 'tax_category' => 'string', 'is_active' => 'boolean'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function details()
    {
        return $this->hasMany(JournalDetail::class);
    }
}
