# Accounting GL

Aplikasi akuntansi perusahaan berbasis Laravel untuk kebutuhan General Ledger internal.

## MVP

Fitur yang sudah tersedia:

- Login, logout, ganti password.
- Role dasar: Super Admin, Admin Perusahaan, Accounting Staff, Finance Staff, Auditor, Viewer.
- Role & Permission granular untuk membatasi menu dan aksi per role.
- Profil Perusahaan.
- Cabang / Branch.
- Periode Akuntansi dengan status Open, Locked, Closed.
- Daftar Akun / Chart of Account dengan hirarki parent account.
- Jurnal Umum / General Journal dengan nomor otomatis `JV-YYYY-000001`.
- Form jurnal mendukung No. Jurnal manual, No. Voucher/Referensi, tanggal transaksi, dan deskripsi wajib. No. Jurnal otomatis dibuat jika dikosongkan.
- Validasi jurnal: minimal 2 baris, debit kredit balance, tidak boleh negatif, satu baris hanya debit atau kredit.
- Workflow jurnal: Draft, Submitted, Approved, Rejected, Posted.
- Posting jurnal hanya dapat dilakukan setelah approval.
- Periode Locked/Closed menolak perubahan jurnal untuk staff biasa.
- Cash & Bank: daftar kas/bank, kas masuk, kas keluar, transfer bank, mutasi, saldo berjalan.
- Transaksi Cash & Bank otomatis membuat jurnal Posted.
- Multi-cabang awal: transaksi jurnal dan cash/bank wajib memilih cabang. Kas bisa scope perusahaan, bank bisa scope cabang.
- Buku Besar / General Ledger.
- Neraca Saldo / Trial Balance.
- Laba Rugi / Profit & Loss.
- Neraca / Balance Sheet.
- Arus Kas / Cash Flow.
- Arus Kas Tidak Langsung / Indirect Cash Flow.
- Closing Entry untuk menutup periode dan membuat jurnal penutup otomatis.
- Rekonsiliasi Bank untuk mencocokkan transaksi bank dengan saldo rekening koran.
- Audit Trail aktivitas penting.
- Export laporan ke Excel-compatible `.xls` dan PDF/Print via halaman cetak browser.
- Backup & Restore database dari menu Pengaturan. Backup dibuat sebagai file `.sql`.

Fitur yang sudah disiapkan tabelnya tetapi belum menjadi alur lengkap: export PDF sungguhan, import Excel Chart of Account.

## Struktur Penting

- `app/Models`: model dan relasi data akuntansi.
- `app/Http/Controllers`: controller auth, dashboard, company, account, journal, report, user.
- `app/Http/Controllers/FiscalPeriodController.php`: pengelolaan periode dan lock/close.
- `app/Http/Controllers/BranchController.php`: master cabang.
- `app/Http/Controllers/BankReconciliationController.php`: rekonsiliasi bank.
- `app/Http/Controllers/ClosingEntryController.php`: jurnal penutup periode.
- `app/Http/Controllers/RolePermissionController.php`: pengaturan hak akses role.
- `app/Http/Middleware/EnsureRole.php`: middleware pembatasan role.
- `app/Http/Middleware/EnsurePermission.php`: middleware permission granular.
- `database/migrations`: skema database.
- `database/seeders/AccountingSeeder.php`: role, company demo, user admin, COA awal.
- `resources/views`: UI Blade Bootstrap.
- `routes/web.php`: route aplikasi.

## Instalasi Lokal

```bash
cd C:\laragon\www\accounting-gl
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Akun awal:

- Email: `admin@example.com`
- Password: `password`

Project sekarang memakai MySQL/MariaDB Laragon dengan database:

```text
accounting_gl
```

Konfigurasi `.env` lokal:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accounting_gl
DB_USERNAME=root
DB_PASSWORD=
```

Untuk inisialisasi database lokal kosong:

```bash
php artisan migrate:fresh --seed
```

Untuk production atau database yang sudah berisi data, jangan gunakan `migrate:fresh`. Gunakan:

```bash
php artisan migrate
php artisan db:seed --class=AccountingSeeder
```

## Role & Permission

Menu:

```text
Pengaturan > Role & Permission
```

Super Admin otomatis memiliki semua akses. Role lain dapat diberi permission seperti `journal.create`, `journal.approve`, `cash_transaction.create`, `report.view`, `backup.manage`, dan lainnya. Sidebar dan beberapa tombol aksi akan mengikuti permission user yang sedang login.

## Deployment VPS Linux

1. Upload project ke server, contoh `/var/www/accounting-gl`.
2. Jalankan `composer install --no-dev --optimize-autoloader`.
3. Buat `.env` production dan set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, serta koneksi database.
4. Jalankan `php artisan key:generate` bila belum ada `APP_KEY`.
5. Jalankan `php artisan migrate --seed`.
6. Set permission:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

7. Arahkan Nginx/Apache document root ke folder `public`.
8. Jalankan optimasi:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Verifikasi

```bash
php artisan test
php artisan route:list
```

## Backup

Menu:

```text
Pengaturan > Backup & Restore
```

Backup dapat dibuat langsung dari aplikasi oleh Super Admin. File SQL disimpan di:

```text
storage/app/backups
```

Restore membutuhkan upload file SQL dan konfirmasi `RESTORE`.
