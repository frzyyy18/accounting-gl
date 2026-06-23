# Accounting GL

Aplikasi akuntansi perusahaan berbasis Laravel untuk kebutuhan General Ledger internal.

## Fitur Utama

Berikut adalah fitur-fitur yang tersedia dalam aplikasi Accounting GL:

- **Autentikasi & Keamanan:** Login, logout, ganti password, Multi-Factor Authentication (MFA), pembatasan rate limit login, session security, serta pengamanan header CSP & HSTS.
- **Manajemen Pengguna & Akses:** Pembagian hak akses granular berbasis Role & Permission (Super Admin, Admin Perusahaan, Accounting Staff, Finance Staff, Auditor, Viewer).
- **Master Data Keuangan:** Pencatatan Profil Perusahaan, pengelolaan Cabang (Branch), serta Periode Akuntansi dengan status dinamis (Open, Locked, Closed).
- **Daftar Akun (Chart of Accounts):** Struktur kode rekening akuntansi berhirarki (parent & child accounts).
- **Jurnal Umum (General Ledger):**
  - Pembuatan jurnal otomatis dengan kode transaksi terurut.
  - Pendukung input No. Jurnal manual, No. Voucher/Referensi, tanggal transaksi, dan deskripsi detail.
  - Validasi jurnal yang ketat (debit & kredit seimbang/balance, nominal positif, minimal 2 baris transaksi).
  - Alur approval jurnal dari status Draft, Submitted, Approved/Rejected, hingga Posted.
- **Kas & Bank:** Pencatatan Kas Masuk, Kas Keluar, dan Transfer Bank antar cabang serta pemantauan saldo berjalan.
- **Rekonsiliasi Bank:** Pencocokan transaksi kas internal dengan rekening koran bank secara sistematis.
- **Laporan Keuangan Otomatis:**
  - Neraca Saldo (Trial Balance).
  - Laporan Buku Besar (General Ledger).
  - Laporan Laba Rugi (Profit & Loss).
  - Laporan Neraca (Balance Sheet).
  - Laporan Arus Kas (Langsung & Tidak Langsung).
  - Riwayat Audit Trail untuk melacak aktivitas pengguna pada transaksi penting.
- **Penutupan Periode (Closing Entry):** Otomatisasi jurnal penutup pada akhir periode akuntansi yang ditentukan.
- **Ekspor Data & Backup:** Ekspor laporan keuangan ke format Excel-compatible dan PDF/Print, serta menu pencadangan/pemulihan database (Backup & Restore SQL).
