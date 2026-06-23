    # Materi Presentasi Accounting GL

## Slide 1 - Accounting GL

Sistem akuntansi internal untuk transaksi, approval, laporan keuangan, audit trail, dan keamanan data perusahaan.

## Slide 2 - Masalah yang Diselesaikan

- Transaksi tersebar di banyak cabang.
- Pencatatan manual rawan salah dan terlambat.
- Approval tidak selalu terdokumentasi.
- Laporan membutuhkan rekap manual.
- Data keuangan perlu dibatasi per role dan perusahaan.

## Slide 3 - Tujuan Aplikasi

- Menyatukan pencatatan transaksi.
- Mengontrol proses approval.
- Membuat laporan keuangan lebih cepat.
- Menjaga audit trail.
- Mengurangi risiko kebocoran data antar perusahaan/cabang.

## Slide 4 - Fitur Utama Saat Ini

- Dashboard keuangan.
- Master data perusahaan, cabang, periode, dan akun.
- Kas dan bank.
- Jurnal umum.
- Rekonsiliasi bank.
- Closing entry.
- Laporan keuangan.
- User, role, permission, backup, dan MFA.

## Slide 5 - Dashboard

Dashboard menampilkan pendapatan bulan ini, beban bulan ini, laba/rugi, pending approval, jurnal posted, akun aktif, grafik bulanan, dan transaksi terakhir.

## Slide 6 - Alur Transaksi Kas/Bank

1. User input penerimaan, pengeluaran, atau transfer.
2. Sistem validasi cabang, akun, periode, dan nominal.
3. Sistem membuat jurnal.
4. Transaksi masuk laporan setelah diposting.

## Slide 7 - Alur Jurnal dan Approval

Status jurnal:

- Draft.
- Submitted.
- Approved.
- Posted.
- Rejected.
- Cancelled.

Workflow ini memisahkan pembuat transaksi, approver, dan user yang melakukan posting.

## Slide 8 - Laporan Keuangan

Laporan yang tersedia:

- Buku Besar.
- Neraca Saldo.
- Laba Rugi.
- Neraca.
- Arus Kas.
- Arus Kas Tidak Langsung.
- Audit Trail.

## Slide 9 - Keamanan

- Rate limit login.
- Session regeneration.
- Secure cookie.
- CSRF protection.
- Authorization policy.
- Permission per role.
- MFA.
- Security headers.
- Backup terenkripsi.
- Audit log.

## Slide 10 - Kesesuaian untuk Struktur Kantor

Struktur target:

- 3 kasir, masing-masing pegang 3 cabang.
- 3 staff pajak, masing-masing pegang 3 cabang.
- 1 manager pajak.

Aplikasi sudah punya fondasi cabang, company, user, role, dan permission. Namun perlu tambahan akses user per cabang.

## Slide 11 - Tambahan yang Diperlukan

- Assignment user ke banyak cabang.
- Filter cabang di seluruh query transaksi dan laporan.
- Role cashier, tax_staff, dan tax_manager.
- Dashboard per role.
- Approval pajak.
- Laporan pajak.

## Slide 12 - Modul Pajak

Belum tersedia lengkap saat ini.

Yang perlu ditambahkan:

- Master jenis pajak.
- Nomor faktur pajak.
- NPWP lawan transaksi.
- PPN Masukan.
- PPN Keluaran.
- PPh.
- Rekap pajak per cabang/periode.
- Approval dan export laporan pajak.

## Slide 13 - Kemudahan untuk User Non-Akuntansi

Supaya mudah untuk kasir dan user non-akuntansi, aplikasi perlu:

- Menu berbasis aktivitas bisnis.
- Template transaksi.
- Form sederhana tanpa debit/kredit.
- Pesan error yang jelas.
- Dashboard sesuai pekerjaan.

## Slide 14 - Roadmap Implementasi

1. Akses user per cabang.
2. Dashboard kasir dan pajak.
3. Modul pajak dasar.
4. Approval pajak.
5. Export laporan pajak.
6. Template transaksi sederhana.

## Slide 15 - Kesimpulan

Accounting GL sudah siap sebagai fondasi sistem akuntansi internal. Untuk struktur kantor multi-cabang dan tim pajak, prioritas berikutnya adalah akses per cabang, dashboard role-based, dan modul pajak.

