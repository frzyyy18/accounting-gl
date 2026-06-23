<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['code', 'module', 'label', 'description'];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public static function catalog(): array
    {
        return [
            'Dashboard' => [
                ['dashboard.view', 'Lihat Dashboard'],
            ],
            'Master Data' => [
                ['company.manage', 'Kelola Perusahaan'],
                ['branch.manage', 'Kelola Cabang'],
                ['period.manage', 'Kelola Periode Akuntansi'],
                ['account.view', 'Lihat Chart of Account'],
                ['account.manage', 'Kelola Chart of Account'],
            ],
            'Transaksi' => [
                ['journal.view', 'Lihat Jurnal Umum'],
                ['journal.create', 'Input/Edit Jurnal Umum'],
                ['journal.approve', 'Approve/Reject Jurnal'],
                ['journal.post', 'Posting Jurnal'],
                ['journal.cancel', 'Batalkan Jurnal'],
                ['journal.delete', 'Hapus Jurnal'],
                ['cash_bank.view', 'Lihat Kas & Bank'],
                ['cash_bank.manage', 'Kelola Kas & Bank'],
                ['cash_transaction.view', 'Lihat Mutasi Kas/Bank'],
                ['cash_transaction.create', 'Input Kas/Bank'],
                ['bank_reconciliation.view', 'Lihat Rekonsiliasi Bank'],
                ['bank_reconciliation.create', 'Buat Rekonsiliasi Bank'],
                ['closing.create', 'Buat Closing Entry'],
            ],
            'Laporan' => [
                ['report.view', 'Lihat Laporan Keuangan'],
                ['tax_report.view', 'Lihat Laporan Pajak'],
                ['audit_trail.view', 'Lihat Audit Trail'],
            ],
            'Pengaturan' => [
                ['settings.manage', 'Kelola Pengaturan Sistem'],
                ['user.manage', 'Kelola User'],
                ['role.manage', 'Kelola Role & Permission'],
                ['backup.manage', 'Backup & Restore'],
            ],
        ];
    }
}
