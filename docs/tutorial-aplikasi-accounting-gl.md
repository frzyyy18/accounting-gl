# Tutorial dan Panduan Presentasi Accounting GL

## 1. Ringkasan Aplikasi

Accounting GL adalah aplikasi akuntansi internal untuk mencatat transaksi perusahaan, mengelola kas dan bank, membuat jurnal, memantau laporan keuangan, dan menjaga audit trail. Aplikasi ini cocok untuk perusahaan multi-cabang yang membutuhkan kontrol akses, proses approval, dan laporan keuangan periodik.

Fitur yang sudah tersedia:

- Dashboard keuangan.
- Master perusahaan, cabang, periode fiskal, dan chart of accounts.
- Kas dan bank.
- Mutasi kas/bank: cash in, cash out, dan transfer.
- Jurnal umum dengan workflow draft, submitted, approved, posted, rejected, dan cancelled.
- Rekonsiliasi bank.
- Closing entry.
- Laporan buku besar, neraca saldo, laba rugi, neraca, arus kas, dan audit trail.
- Manajemen user, role, dan permission.
- Backup dan restore database.
- Multi-factor authentication.
- Pengamanan dasar seperti rate limit login, session security, CSRF, authorization, CSP, HSTS, dan audit log.

Fitur yang belum lengkap:

- Laporan pajak khusus seperti PPN Masukan, PPN Keluaran, PPh, faktur pajak, dan export e-Faktur/e-Bupot.
- Pembagian akses user per cabang secara detail.
- Dashboard khusus kasir, staff pajak, dan manager pajak.
- Workflow approval khusus pajak.
- Template transaksi sederhana untuk user non-akuntansi.

## 2. Siapa yang Memakai Aplikasi

### Owner atau Direktur

Fokus utama:

- Melihat ringkasan pendapatan, beban, laba/rugi, dan posisi kas.
- Memantau transaksi pending approval.
- Membuka laporan laba rugi, neraca, dan arus kas.

### Accounting atau Finance

Fokus utama:

- Input jurnal.
- Posting transaksi.
- Rekonsiliasi bank.
- Closing periode.
- Export laporan.

### Kasir

Fokus utama:

- Input penerimaan kas.
- Input pengeluaran kas.
- Upload bukti transaksi.
- Melihat saldo kas/bank cabang yang menjadi tanggung jawabnya.

Catatan: aplikasi perlu ditambah assignment user ke cabang agar kasir hanya melihat cabang yang dia pegang.

### Staff Pajak

Fokus utama:

- Memeriksa transaksi yang berkaitan dengan pajak.
- Melengkapi nomor faktur, NPWP lawan transaksi, dan jenis pajak.
- Membuat rekap pajak per cabang.

Catatan: modul pajak khusus belum tersedia dan perlu ditambahkan.

### Manager Pajak

Fokus utama:

- Memantau rekap pajak semua cabang.
- Approve laporan pajak.
- Export rekap pajak.
- Melihat audit perubahan data pajak.

## 3. Alur Penggunaan Dasar

### Langkah 1: Login

1. Buka halaman login.
2. Masukkan email dan password.
3. Jika MFA aktif, masukkan kode dari aplikasi authenticator.
4. Setelah berhasil, sistem akan masuk ke dashboard.

Keamanan login:

- Login dibatasi dengan rate limit `5 percobaan per menit`.
- Session di-regenerate setelah login berhasil.
- Login gagal dicatat di audit log.
- MFA tersedia untuk meningkatkan keamanan akun.

### Langkah 2: Setup Data Awal

Urutan setup yang disarankan:

1. Buat atau lengkapi data perusahaan.
2. Buat cabang.
3. Buat periode fiskal.
4. Buat chart of accounts.
5. Buat akun kas/bank.
6. Buat user dan role.
7. Atur permission sesuai jabatan.

Data awal ini penting karena transaksi memakai cabang, akun, periode, dan user sebagai dasar kontrol.

### Langkah 3: Input Kas dan Bank

Untuk transaksi kas/bank:

1. Buka menu kas/bank transaction.
2. Pilih tipe transaksi: cash in, cash out, atau transfer.
3. Isi tanggal transaksi.
4. Pilih cabang.
5. Pilih kas/bank.
6. Isi nominal dan keterangan.
7. Upload bukti jika tersedia.
8. Simpan.

Sistem akan membuat jurnal otomatis untuk transaksi kas/bank sesuai konfigurasi akun.

### Langkah 4: Input Jurnal Umum

Gunakan jurnal umum untuk transaksi yang tidak masuk workflow kas/bank sederhana.

1. Buka menu jurnal.
2. Klik tambah jurnal.
3. Isi tanggal, cabang, referensi, dan keterangan.
4. Tambahkan baris akun.
5. Isi debit dan kredit.
6. Pastikan total debit sama dengan total kredit.
7. Simpan sebagai draft atau submit.

Status jurnal:

- Draft: masih bisa diedit.
- Submitted: menunggu approval.
- Approved: sudah disetujui.
- Posted: masuk laporan dan tidak boleh diedit.
- Rejected: ditolak.
- Cancelled: dibatalkan.

### Langkah 5: Approval dan Posting

1. User pembuat transaksi melakukan submit.
2. User dengan permission approve melakukan approve atau reject.
3. User dengan permission posting melakukan post.
4. Setelah posted, transaksi masuk laporan.

Manfaat workflow ini:

- Mengurangi salah input.
- Memisahkan pembuat dan penyetuju.
- Membuat audit trail lebih jelas.

### Langkah 6: Rekonsiliasi Bank

1. Buka menu rekonsiliasi bank.
2. Pilih akun bank.
3. Isi tanggal rekening koran.
4. Isi saldo rekening koran.
5. Pilih transaksi yang cocok.
6. Simpan rekonsiliasi.

Tujuan rekonsiliasi:

- Mencocokkan catatan sistem dengan rekening bank.
- Menemukan transaksi belum tercatat.
- Mengurangi risiko selisih kas/bank.

### Langkah 7: Melihat Laporan

Laporan yang tersedia:

- Buku Besar: detail pergerakan akun.
- Neraca Saldo: total debit, kredit, dan saldo akun.
- Laba Rugi: pendapatan, beban, dan laba/rugi bersih.
- Neraca: aset, kewajiban, dan ekuitas.
- Arus Kas: kas masuk dan kas keluar.
- Arus Kas Tidak Langsung: arus kas berdasarkan laba/rugi dan penyesuaian.
- Audit Trail: riwayat aktivitas penting.

Filter laporan:

- Periode tanggal.
- Cabang.
- Akun.
- Status transaksi.
- Periode pembanding.

Laporan dapat diexport ke Excel atau PDF/print pada beberapa halaman laporan.

### Langkah 8: Closing Periode

Closing dilakukan setelah periode selesai dan transaksi sudah valid.

1. Pastikan jurnal periode tersebut sudah posted.
2. Buka menu closing entry.
3. Pilih periode fiskal.
4. Pilih akun ekuitas tujuan.
5. Simpan closing.

Setelah closing, periode dapat dikunci agar transaksi lama tidak berubah sembarangan.

### Langkah 9: Backup

1. Buka menu backup.
2. Klik buat dan download backup.
3. Simpan file backup di lokasi aman.

Catatan keamanan:

- Backup sudah diarahkan untuk terenkripsi.
- Akses backup hanya untuk role yang memiliki permission.
- File backup harus disimpan di storage yang aman.

## 4. Role dan Permission yang Disarankan

| Role | Fungsi | Akses Utama |
| --- | --- | --- |
| Super Admin | Pengelola penuh sistem | Semua akses |
| Admin Perusahaan | Setup perusahaan | Master data, user, role tertentu |
| Accounting Staff | Input transaksi | Jurnal draft, kas/bank, laporan terbatas |
| Finance Manager | Approval dan monitoring | Approve, post, laporan |
| Viewer | Lihat laporan | Dashboard dan laporan |
| Auditor | Audit internal | Laporan dan audit trail |
| Cashier | Kasir cabang | Input kas/bank cabang tertentu |
| Tax Staff | Staff pajak cabang | Data dan laporan pajak cabang tertentu |
| Tax Manager | Manager pajak | Approval dan rekap pajak semua cabang |

Role Cashier, Tax Staff, dan Tax Manager perlu ditambahkan jika aplikasi akan dipakai sesuai struktur kantor yang memiliki banyak cabang dan tim pajak.

## 5. Kebutuhan Tambahan untuk Struktur Kantor Multi-Cabang

Struktur yang disebutkan:

- 3 kasir, masing-masing memegang 3 cabang.
- 3 staff pajak, masing-masing memegang 3 cabang.
- 1 manager pajak.

Tambahan yang disarankan:

### Assignment User ke Cabang

Buat relasi user ke banyak cabang:

- `user_branches`
- `user_id`
- `branch_id`

Manfaat:

- Kasir hanya melihat cabang yang menjadi tanggung jawabnya.
- Staff pajak hanya mengelola cabang yang ditugaskan.
- Manager pajak dapat melihat semua cabang pajak.

### Filter Data Berdasarkan Cabang

Semua transaksi dan laporan harus dicek berdasarkan cabang yang diizinkan.

Aturan penting:

- Jangan hanya menyembunyikan menu.
- Query database juga harus difilter.
- Akses URL manual ke cabang lain harus ditolak.

### Dashboard Per Role

Kasir:

- Saldo kas cabang.
- Transaksi hari ini.
- Transaksi belum approval.
- Bukti transaksi belum upload.

Staff pajak:

- Transaksi belum lengkap data pajak.
- Faktur pajak belum diisi.
- PPN/PPh per cabang.
- Laporan belum submit.

Manager pajak:

- Rekap semua cabang.
- Cabang belum submit laporan pajak.
- Approval pajak.
- Export laporan pajak.

## 6. Modul Pajak yang Perlu Ditambahkan

Modul pajak belum termasuk lengkap di aplikasi saat ini. Yang perlu ditambahkan:

### Master Pajak

- Jenis pajak: PPN, PPh 21, PPh 23, PPh Final, dan jenis lain sesuai kebutuhan.
- Tarif pajak.
- Akun pajak terkait.
- Status aktif/nonaktif.

### Data Pajak di Transaksi

- NPWP lawan transaksi.
- Nama lawan transaksi.
- Nomor faktur pajak.
- Tanggal faktur pajak.
- DPP.
- Nilai PPN.
- Jenis dan nilai PPh.
- Status validasi pajak.

### Laporan Pajak

- PPN Masukan.
- PPN Keluaran.
- Selisih PPN kurang/lebih bayar.
- Rekap PPh.
- Rekap pajak per cabang.
- Rekap pajak per periode.

### Workflow Pajak

1. Staff pajak melengkapi data pajak.
2. Staff pajak submit laporan pajak cabang.
3. Manager pajak review.
4. Manager pajak approve atau reject.
5. Setelah approve, laporan dapat diexport.

## 7. Apakah Mudah untuk User Non-Akuntansi?

Untuk user accounting, aplikasi sudah cukup familiar.

Untuk user non-akuntansi, masih perlu disederhanakan dengan:

- Menu berbasis aktivitas bisnis.
- Template transaksi.
- Penjelasan pesan error yang lebih manusiawi.
- Dashboard khusus role.
- Form kasir yang tidak menampilkan istilah debit/kredit.
- Wizard atau langkah-langkah transaksi.

Contoh label yang lebih mudah:

- `Penerimaan Uang` dibanding `Cash In`.
- `Pengeluaran Uang` dibanding `Cash Out`.
- `Kunci ke Laporan` dibanding `Posting`.
- `Butuh Persetujuan` dibanding `Submitted`.

## 8. Materi Presentasi Singkat

Gunakan alur berikut saat presentasi:

1. Masalah bisnis: transaksi banyak cabang, kontrol akses, risiko salah input, dan laporan manual.
2. Solusi: Accounting GL sebagai pusat pencatatan, approval, laporan, dan audit.
3. Demo dashboard.
4. Demo input kas/bank.
5. Demo jurnal dan approval.
6. Demo laporan.
7. Demo user, role, dan keamanan.
8. Jelaskan gap: modul pajak dan akses per cabang perlu ditambahkan.
9. Roadmap implementasi.
10. Tanya jawab.

## 9. Roadmap yang Disarankan

Prioritas 1:

- User assignment ke cabang.
- Filter cabang di semua transaksi dan laporan.
- Role cashier, tax_staff, dan tax_manager.

Prioritas 2:

- Dashboard kasir.
- Dashboard staff pajak.
- Dashboard manager pajak.

Prioritas 3:

- Modul master pajak.
- Input data pajak di transaksi.
- Laporan PPN dan PPh.

Prioritas 4:

- Workflow approval pajak.
- Export laporan pajak.
- Alert transaksi tidak wajar.

Prioritas 5:

- Template transaksi sederhana untuk user non-akuntansi.
- Wizard onboarding dan tutorial in-app.

