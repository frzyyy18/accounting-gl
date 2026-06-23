const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  HeadingLevel, AlignmentType, BorderStyle, WidthType, ShadingType,
  VerticalAlign, PageNumber, PageBreak, LevelFormat, Header, Footer,
  TabStopType, TabStopPosition,
} = await import("docx");
const fs = await import("node:fs");
const path = await import("node:path");
const { fileURLToPath } = await import("node:url");

const outputDir = path.dirname(fileURLToPath(import.meta.url));

const NAVY = "0F2D55";
const TEAL = "0D7E6A";
const GRAY = "64748B";
const LIGHT = "EBF4F2";

const border = { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" };
const borders = { top: border, bottom: border, left: border, right: border };

const pageProps = {
  size: { width: 12240, height: 15840 },
  margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 },
};

const numbering = {
  config: [
    {
      reference: "bullets",
      levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 720, hanging: 360 } } } }],
    },
    {
      reference: "subbullets",
      levels: [{ level: 0, format: LevelFormat.BULLET, text: "–", alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 1080, hanging: 360 } } } }],
    },
    {
      reference: "numbers",
      levels: [{ level: 0, format: LevelFormat.DECIMAL, text: "%1.", alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 720, hanging: 360 } } } }],
    },
  ],
};

const styles = {
  default: { document: { run: { font: "Calibri", size: 24 } } },
  paragraphStyles: [
    { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
      run: { size: 36, bold: true, font: "Calibri", color: NAVY },
      paragraph: { spacing: { before: 360, after: 200 }, outlineLevel: 0 } },
    { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
      run: { size: 28, bold: true, font: "Calibri", color: TEAL },
      paragraph: { spacing: { before: 280, after: 140 }, outlineLevel: 1 } },
    { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true,
      run: { size: 24, bold: true, font: "Calibri", color: NAVY },
      paragraph: { spacing: { before: 200, after: 100 }, outlineLevel: 2 } },
  ],
};

function h1(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun({ text, font: "Calibri", size: 36, bold: true, color: NAVY })] });
}
function h2(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text, font: "Calibri", size: 28, bold: true, color: TEAL })] });
}
function h3(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun({ text, font: "Calibri", size: 24, bold: true, color: NAVY })] });
}
function p(text, opts = {}) {
  return new Paragraph({ spacing: { after: 160 }, children: [new TextRun({ text, font: "Calibri", size: 24, ...opts })] });
}
function bullet(text) {
  return new Paragraph({ numbering: { reference: "bullets", level: 0 }, spacing: { after: 100 }, children: [new TextRun({ text, font: "Calibri", size: 24 })] });
}
function subbullet(text) {
  return new Paragraph({ numbering: { reference: "subbullets", level: 0 }, spacing: { after: 80 }, children: [new TextRun({ text, font: "Calibri", size: 22, color: GRAY })] });
}
function numbered(text) {
  return new Paragraph({ numbering: { reference: "numbers", level: 0 }, spacing: { after: 120 }, children: [new TextRun({ text, font: "Calibri", size: 24 })] });
}
function space() {
  return new Paragraph({ spacing: { after: 160 } });
}
function pageBreak() {
  return new Paragraph({ children: [new PageBreak()] });
}

function tableRow(cells, isHeader = false) {
  return new TableRow({
    tableHeader: isHeader,
    children: cells.map((cell, idx) => new TableCell({
      borders,
      width: { size: Math.floor(9360 / cells.length), type: WidthType.DXA },
      shading: isHeader ? { fill: NAVY, type: ShadingType.CLEAR } : (idx % 2 === 0 ? { fill: "F8FAFB", type: ShadingType.CLEAR } : { fill: "FFFFFF", type: ShadingType.CLEAR }),
      margins: { top: 80, bottom: 80, left: 150, right: 150 },
      verticalAlign: VerticalAlign.CENTER,
      children: [new Paragraph({ children: [new TextRun({
        text: cell, font: "Calibri", size: 22,
        bold: isHeader, color: isHeader ? "FFFFFF" : "1E293B",
      })] })],
    })),
  });
}

function makeTable(headers, rows) {
  return new Table({
    width: { size: 9360, type: WidthType.DXA },
    columnWidths: headers.map(() => Math.floor(9360 / headers.length)),
    rows: [
      tableRow(headers, true),
      ...rows.map(r => tableRow(r)),
    ],
  });
}

// ═══════════════════════════════════════════════════════════════════
// DOKUMEN 1: EXECUTIVE SUMMARY / PROPOSAL
// ═══════════════════════════════════════════════════════════════════
const proposalDoc = new Document({
  styles, numbering,
  sections: [{
    properties: { page: pageProps },
    children: [
      // Cover
      new Paragraph({
        spacing: { before: 1440, after: 280 },
        children: [new TextRun({ text: "ACCOUNTING GL", font: "Calibri", size: 64, bold: true, color: NAVY })],
      }),
      new Paragraph({
        spacing: { after: 200 },
        children: [new TextRun({ text: "Sistem Informasi Akuntansi Perusahaan", font: "Calibri", size: 32, color: TEAL, italic: true })],
      }),
      new Paragraph({
        spacing: { after: 120 },
        children: [new TextRun({ text: "Executive Summary & Proposal", font: "Calibri", size: 28, bold: true, color: GRAY })],
      }),
      new Paragraph({
        spacing: { after: 800 },
        children: [new TextRun({ text: "Versi 1.0  ·  Mei 2026", font: "Calibri", size: 22, color: GRAY })],
      }),
      new Paragraph({
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: TEAL } },
        spacing: { after: 480 },
        children: [],
      }),

      // Ringkasan Eksekutif
      h1("1. Ringkasan Eksekutif"),
      p("Accounting GL adalah sistem informasi akuntansi berbasis web yang dirancang khusus untuk perusahaan dengan struktur multi-cabang. Sistem ini mengotomasi seluruh siklus akuntansi mulai dari pencatatan jurnal, pengelolaan kas & bank, rekonsiliasi bank, hingga pelaporan keuangan standar dan pajak."),
      p("Sistem dibangun di atas Laravel (PHP) dengan antarmuka berbasis Blade/Bootstrap yang responsif, mendukung multi-perusahaan dan multi-cabang dalam satu instalasi, serta dilengkapi dengan lapisan keamanan berlapis termasuk autentikasi dua faktor (MFA), header keamanan HTTP, dan audit trail komprehensif."),
      space(),

      // Masalah yang Diselesaikan
      h2("1.1 Masalah yang Diselesaikan"),
      bullet("Pencatatan akuntansi yang tersebar di berbagai file spreadsheet tanpa kontrol versi"),
      bullet("Tidak ada mekanisme approval bertingkat untuk transaksi keuangan"),
      bullet("Laporan keuangan yang dibuat secara manual dan rentan kesalahan"),
      bullet("Tidak ada jejak audit untuk setiap perubahan data keuangan"),
      bullet("Pengelolaan pajak (PPN, PPh) yang tidak terintegrasi dengan pembukuan"),
      space(),

      // Solusi
      h2("1.2 Solusi yang Ditawarkan"),
      bullet("Workflow approval jurnal 4 tahap: Draft → Submit → Approve → Post"),
      bullet("Dashboard real-time untuk ringkasan keuangan dan status transaksi"),
      bullet("Dashboard pajak terpisah khusus divisi perpajakan"),
      bullet("Laporan keuangan otomatis: Neraca, L/R, Arus Kas, Buku Besar"),
      bullet("Rekonsiliasi bank terintegrasi dengan mutasi kas & bank"),
      bullet("Backup terenkripsi dan pemulihan data mandiri"),
      space(),

      pageBreak(),

      // Cakupan Fitur
      h1("2. Cakupan Fitur Utama"),
      space(),

      h2("2.1 Modul Transaksi"),
      p("Sistem menyediakan tiga jalur pencatatan transaksi yang terintegrasi:"),
      space(),
      h3("Jurnal Umum"),
      bullet("Input jurnal dengan detail akun debit/kredit"),
      bullet("Lampiran dokumen pendukung (attachment)"),
      bullet("Nomor voucher otomatis per perusahaan dan cabang"),
      bullet("Deteksi anomali transaksi otomatis"),
      bullet("Workflow approval dengan pemisahan tugas (4-eyes principle)"),
      space(),
      h3("Kas & Bank"),
      bullet("Kas Masuk, Bank Masuk, Kas Keluar, dan Transfer Bank"),
      bullet("Reverse transaksi untuk koreksi"),
      bullet("Saldo berjalan (running balance) per periode"),
      bullet("Laporan mutasi kas dan bank per akun"),
      space(),
      h3("Piutang Usaha"),
      bullet("Pencatatan tagihan toko (store receivable)"),
      bullet("Penerimaan pembayaran piutang multi-akun"),
      bullet("Integrasi langsung ke General Ledger"),
      space(),

      h2("2.2 Rekonsiliasi Bank"),
      bullet("Pencocokan transaksi buku dengan laporan bank"),
      bullet("Identifikasi transaksi yang belum direkonsiliasi"),
      bullet("Pencatatan selisih rekonsiliasi"),
      bullet("Riwayat rekonsiliasi per akun bank"),
      space(),

      h2("2.3 Penutupan Periode"),
      bullet("Closing entry otomatis untuk laba/rugi ke ekuitas"),
      bullet("Lock, unlock, dan close periode akuntansi"),
      bullet("Pencegahan transaksi di periode yang sudah dikunci"),
      space(),

      pageBreak(),

      h1("3. Laporan Keuangan & Pajak"),
      space(),
      h2("3.1 Laporan Keuangan Standar"),
      makeTable(
        ["Laporan", "Deskripsi", "Format Export"],
        [
          ["Buku Besar", "Rincian transaksi per akun dengan saldo berjalan", "PDF, Excel"],
          ["Neraca Saldo", "Ringkasan debit/kredit semua akun", "PDF, Excel"],
          ["Laba Rugi", "Pendapatan vs beban, laba/rugi bersih", "PDF, Excel"],
          ["Neraca", "Aset, kewajiban, dan ekuitas", "PDF, Excel"],
          ["Arus Kas Langsung", "Penerimaan dan pengeluaran riil", "PDF, Excel"],
          ["Arus Kas Tidak Langsung", "Rekonsiliasi laba ke arus kas", "PDF, Excel"],
          ["Audit Trail", "Jejak semua perubahan data", "PDF, Excel"],
        ]
      ),
      space(),
      h2("3.2 Laporan Pajak"),
      bullet("Dashboard PPN: saldo hutang PPN, tren bulanan, perbandingan periode"),
      bullet("Dashboard PPh 21, PPh 23, dan PPh Final"),
      bullet("Laporan summary pajak per akun dan periode"),
      bullet("Rekonsiliasi pajak: deteksi selisih antara GL dan pembayaran ke DJP"),
      bullet("Filter per perusahaan, cabang, dan rentang tanggal"),
      space(),

      pageBreak(),

      h1("4. Keamanan & Kepatuhan"),
      space(),
      h2("4.1 Autentikasi & Otorisasi"),
      bullet("Multi-Factor Authentication (MFA) menggunakan Google Authenticator"),
      bullet("Throttle login: maksimal 5 percobaan per menit"),
      bullet("Role-based access control (RBAC) dengan 7 role bawaan"),
      bullet("Permission granular per fitur (20+ permission tersedia)"),
      bullet("Session regeneration setelah login untuk mencegah session fixation"),
      space(),
      h2("4.2 Keamanan HTTP"),
      bullet("Content Security Policy (CSP) mencegah XSS"),
      bullet("HTTP Strict Transport Security (HSTS) untuk HTTPS wajib di produksi"),
      bullet("X-Frame-Options: DENY mencegah clickjacking"),
      bullet("X-Content-Type-Options: nosniff"),
      bullet("Referrer Policy dan Permissions Policy"),
      space(),
      h2("4.3 Audit & Kepatuhan"),
      bullet("Audit trail mencatat setiap aksi: login, logout, buat, edit, hapus, export"),
      bullet("Approval log untuk setiap perubahan status jurnal"),
      bullet("Deteksi anomali transaksi mencurigakan"),
      bullet("Backup database terenkripsi menggunakan APP_KEY Laravel"),
      space(),

      pageBreak(),

      h1("5. Manajemen Pengguna & Organisasi"),
      space(),
      makeTable(
        ["Role", "Akses Utama"],
        [
          ["Super Admin", "Akses penuh ke semua fitur dan semua perusahaan"],
          ["Manager Internal", "Jurnal, Kas/Bank, Approve, Posting, Closing, Manajemen User"],
          ["OmM", "Setara Manager Internal, fokus operasional"],
          ["Manager Pajak", "Jurnal, Laporan, Dashboard Pajak, Approve Jurnal"],
          ["Admin Pajak", "Input Jurnal, Laporan Pajak, View Kas/Bank"],
          ["Kasir Cabang", "Input Kas/Bank, View Dashboard dan Laporan"],
          ["Auditor", "Read-only — semua operasi tulis diblok otomatis oleh sistem"],
        ]
      ),
      space(),
      p("Setiap perusahaan dapat memiliki cabang-cabang yang masing-masing memiliki data terisolasi namun dapat dikonsolidasikan dalam laporan tingkat perusahaan."),
      space(),

      pageBreak(),

      h1("6. Rekomendasi & Roadmap"),
      space(),
      h2("6.1 Perbaikan Prioritas Tinggi"),
      bullet("Reset password via email: diperlukan segera agar pengguna dapat mandiri tanpa bergantung admin"),
      bullet("Notifikasi email: alert otomatis ke approver saat jurnal disubmit atau status berubah"),
      bullet("Tipe transaksi bank_out: untuk mencatat pembayaran langsung via transfer bank"),
      space(),
      h2("6.2 Pengembangan Jangka Menengah"),
      bullet("Laporan perbandingan periode (comparative) untuk analisis tren manajemen"),
      bullet("Halaman profil user self-service untuk update data pribadi"),
      bullet("Validasi closing entry: peringatan jika masih ada jurnal pending di periode"),
      space(),
      h2("6.3 Pengembangan Jangka Panjang"),
      bullet("Modul Hutang Usaha (Accounts Payable) untuk pengelolaan tagihan supplier"),
      bullet("Anggaran vs Realisasi (Budgeting) untuk monitoring target keuangan"),
      bullet("API publik untuk integrasi dengan sistem ERP atau POS eksternal"),
      space(),

      new Paragraph({
        border: { top: { style: BorderStyle.SINGLE, size: 4, color: TEAL } },
        spacing: { before: 480, after: 200 },
        children: [new TextRun({ text: "Accounting GL · Executive Summary · Mei 2026", font: "Calibri", size: 20, color: GRAY, italic: true })],
      }),
    ],
  }],
});

// ═══════════════════════════════════════════════════════════════════
// DOKUMEN 2: USER MANUAL / PANDUAN PENGGUNA
// ═══════════════════════════════════════════════════════════════════
const userManualDoc = new Document({
  styles, numbering,
  sections: [{
    properties: { page: pageProps },
    children: [
      new Paragraph({
        spacing: { before: 1440, after: 280 },
        children: [new TextRun({ text: "PANDUAN PENGGUNA", font: "Calibri", size: 56, bold: true, color: NAVY })],
      }),
      new Paragraph({
        spacing: { after: 200 },
        children: [new TextRun({ text: "Accounting GL — Sistem Akuntansi Perusahaan", font: "Calibri", size: 30, color: TEAL, italic: true })],
      }),
      new Paragraph({
        spacing: { after: 800 },
        children: [new TextRun({ text: "Versi 1.0  ·  Untuk semua pengguna", font: "Calibri", size: 22, color: GRAY })],
      }),
      new Paragraph({
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: TEAL } },
        spacing: { after: 480 },
        children: [],
      }),

      h1("1. Memulai — Login & Keamanan"),
      space(),
      h2("1.1 Login ke Sistem"),
      numbered("Buka browser dan akses URL aplikasi Accounting GL"),
      numbered("Masukkan Email dan Password yang telah diberikan administrator"),
      numbered("Klik tombol Masuk"),
      numbered("Jika akun menggunakan MFA: buka aplikasi Google Authenticator di smartphone Anda"),
      numbered("Masukkan kode 6 digit yang ditampilkan — kode berlaku selama 30 detik"),
      numbered("Klik Verifikasi untuk masuk ke dashboard"),
      space(),
      p("Catatan penting:", { bold: true }),
      bullet("Sistem akan mengunci akun sementara setelah 5 kali percobaan login yang gagal"),
      bullet("Jika lupa password, hubungi administrator untuk reset manual"),
      bullet("Selalu klik Keluar setelah selesai menggunakan sistem, terutama di komputer bersama"),
      space(),

      h2("1.2 Mengaktifkan MFA (Autentikasi Dua Faktor)"),
      p("MFA sangat direkomendasikan untuk melindungi akun Anda dari akses tidak sah."),
      numbered("Klik nama pengguna di pojok kanan atas"),
      numbered("Pilih Pengaturan MFA"),
      numbered("Install aplikasi Google Authenticator di smartphone (Android / iOS)"),
      numbered("Scan QR Code yang ditampilkan sistem menggunakan aplikasi"),
      numbered("Masukkan kode 6 digit dari aplikasi untuk mengkonfirmasi"),
      numbered("MFA kini aktif — setiap login akan meminta kode dari aplikasi"),
      space(),

      h2("1.3 Mengganti Password"),
      numbered("Klik nama pengguna di pojok kanan atas"),
      numbered("Pilih Ganti Password"),
      numbered("Masukkan password lama, password baru, dan konfirmasi password baru"),
      numbered("Password minimal 8 karakter, mengandung huruf besar, huruf kecil, angka, dan simbol"),
      numbered("Klik Simpan"),
      space(),

      pageBreak(),

      h1("2. Dashboard"),
      space(),
      h2("2.1 Dashboard Utama"),
      p("Dashboard menampilkan ringkasan keuangan bulan berjalan secara real-time:"),
      bullet("Pendapatan Bulan Ini: total pendapatan dari jurnal yang sudah diposting"),
      bullet("Beban Bulan Ini: total beban dari jurnal yang sudah diposting"),
      bullet("Laba Bersih: selisih pendapatan dikurangi beban"),
      bullet("Jurnal Menunggu Approval: jumlah jurnal submitted yang perlu disetujui"),
      bullet("Grafik tren 6 bulan terakhir (pendapatan, beban, laba)"),
      bullet("Status jurnal berdasarkan kategori (draft, submitted, approved, posted)"),
      bullet("Daftar jurnal terbaru"),
      space(),

      h2("2.2 Dashboard Pajak"),
      p("Khusus untuk pengguna dengan role Manager Pajak atau Admin Pajak. Dashboard ini menampilkan:"),
      bullet("Ringkasan hutang PPN, PPh 21, PPh 23, dan PPh Final bulan berjalan"),
      bullet("Perbandingan dengan bulan sebelumnya (naik/turun dalam persen)"),
      bullet("Laba sebelum pajak dan estimasi pajak penghasilan badan"),
      bullet("Jurnal yang memerlukan tindakan (draft lama, rejected)"),
      space(),

      pageBreak(),

      h1("3. Jurnal Umum"),
      space(),
      h2("3.1 Membuat Jurnal Baru"),
      numbered("Di menu sidebar, klik Jurnal Umum → Tambah Jurnal"),
      numbered("Isi Tanggal Transaksi"),
      numbered("Pilih Cabang yang sesuai"),
      numbered("Nomor Voucher akan otomatis terisi — biarkan kosong jika ingin otomatis"),
      numbered("Isi Nomor Referensi (opsional, misal: nomor faktur)"),
      numbered("Isi Keterangan/Deskripsi transaksi"),
      numbered("Upload lampiran jika ada (opsional)"),
      numbered("Tambahkan baris detail jurnal:"),
      subbullet("Pilih akun dari dropdown"),
      subbullet("Masukkan jumlah di kolom Debit atau Kredit"),
      subbullet("Isi keterangan baris (opsional)"),
      subbullet("Klik + untuk menambah baris"),
      numbered("Pastikan total Debit = total Kredit (sistem akan memvalidasi otomatis)"),
      numbered("Pilih status: Simpan sebagai Draft atau Langsung Submit"),
      numbered("Klik Simpan"),
      space(),

      h2("3.2 Alur Persetujuan Jurnal"),
      p("Setiap jurnal melewati tahapan berikut sebelum masuk ke laporan keuangan:"),
      space(),
      makeTable(
        ["Status", "Deskripsi", "Siapa yang Bisa Bertindak"],
        [
          ["Draft", "Jurnal baru dibuat, belum dikirim", "Pembuat jurnal"],
          ["Submitted", "Dikirim untuk persetujuan", "Approver (bukan pembuat jurnal)"],
          ["Approved", "Disetujui, siap diposting", "User dengan izin journal.post"],
          ["Posted", "Masuk ke GL dan laporan keuangan", "Sistem (tidak bisa diubah)"],
          ["Rejected", "Ditolak, perlu revisi", "Pembuat jurnal (edit dan submit ulang)"],
          ["Cancelled", "Dibatalkan permanen", "User dengan izin journal.cancel"],
        ]
      ),
      space(),
      p("Catatan penting:", { bold: true }),
      bullet("Pembuat jurnal TIDAK BISA menyetujui jurnal yang ia buat sendiri"),
      bullet("Jurnal yang sudah Posted tidak bisa diedit atau dihapus"),
      bullet("Pembatalan (Cancel) memerlukan alasan minimal 10 karakter"),
      space(),

      h2("3.3 Menyetujui atau Menolak Jurnal"),
      numbered("Di dashboard, klik jurnal yang berstatus Submitted"),
      numbered("Periksa detail jurnal dan lampiran"),
      numbered("Untuk menyetujui: klik tombol Approve"),
      numbered("Untuk menolak: klik Reject, isi alasan penolakan, klik Konfirmasi"),
      numbered("Jurnal yang diapprove selanjutnya dapat diposting oleh user yang berwenang"),
      space(),

      pageBreak(),

      h1("4. Kas & Bank"),
      space(),
      h2("4.1 Mencatat Transaksi Kas/Bank"),
      p("Ada empat jenis transaksi yang dapat dicatat:"),
      makeTable(
        ["Jenis", "Kapan Digunakan", "Akun yang Terpengaruh"],
        [
          ["Kas Masuk", "Penerimaan uang tunai", "Debit Kas, Kredit akun lawan"],
          ["Bank Masuk", "Penerimaan ke rekening bank", "Debit Bank, Kredit akun lawan"],
          ["Kas Keluar", "Pengeluaran uang tunai", "Debit akun lawan, Kredit Kas"],
          ["Transfer Bank", "Pemindahan dana antar rekening", "Debit Bank tujuan, Kredit Bank asal"],
        ]
      ),
      space(),
      h3("Langkah Mencatat Transaksi:"),
      numbered("Di menu sidebar, klik Mutasi Kas/Bank"),
      numbered("Klik Tambah Transaksi, pilih jenis transaksi"),
      numbered("Isi Tanggal, Cabang, Akun Kas/Bank"),
      numbered("Isi jumlah dan keterangan"),
      numbered("Upload lampiran jika ada"),
      numbered("Klik Simpan"),
      space(),

      h2("4.2 Melihat Mutasi & Saldo"),
      bullet("Masuk ke menu Mutasi Kas/Bank"),
      bullet("Filter berdasarkan jenis (kas/bank), cabang, dan periode tanggal"),
      bullet("Sistem menampilkan saldo awal, daftar transaksi, dan saldo akhir"),
      bullet("Saldo berjalan dihitung otomatis per baris transaksi"),
      space(),

      h2("4.3 Reverse Transaksi"),
      p("Jika ada kesalahan input transaksi yang sudah tersimpan:"),
      numbered("Buka detail transaksi yang ingin dikoreksi"),
      numbered("Klik tombol Reverse Transaksi"),
      numbered("Isi keterangan alasan reverse"),
      numbered("Sistem akan membuat transaksi pembalik otomatis"),
      p("Catatan: Reverse tidak menghapus transaksi asli, melainkan membuat entri negatif baru untuk menetralkan efeknya."),
      space(),

      pageBreak(),

      h1("5. Rekonsiliasi Bank"),
      space(),
      h2("5.1 Membuat Rekonsiliasi Bank"),
      p("Rekonsiliasi bank dilakukan untuk mencocokkan saldo buku dengan laporan bank dari bank."),
      numbered("Di menu sidebar, klik Rekonsiliasi Bank → Tambah"),
      numbered("Pilih Akun Bank yang akan direkonsiliasi"),
      numbered("Masukkan Tanggal Laporan Bank"),
      numbered("Masukkan Saldo Akhir sesuai laporan bank (rekening koran)"),
      numbered("Sistem menampilkan daftar transaksi yang belum direkonsiliasi"),
      numbered("Centang transaksi yang sudah muncul di laporan bank"),
      numbered("Sistem otomatis menghitung selisih rekonsiliasi"),
      numbered("Isi catatan jika diperlukan, lalu klik Simpan"),
      space(),

      h1("6. Laporan Keuangan"),
      space(),
      h2("6.1 Mengakses Laporan"),
      bullet("Di menu sidebar, klik Laporan"),
      bullet("Pilih jenis laporan yang diinginkan"),
      bullet("Atur filter: Perusahaan, Cabang, Periode Dari, Periode Sampai"),
      bullet("Klik Tampilkan untuk memuat laporan"),
      bullet("Klik Ekspor PDF atau Ekspor Excel untuk mengunduh"),
      space(),

      h2("6.2 Tips Penggunaan Laporan"),
      bullet("Buku Besar: filter per akun untuk melihat rincian transaksi akun tertentu"),
      bullet("Neraca Saldo: gunakan untuk rekonsiliasi akhir periode sebelum menyusun laporan formal"),
      bullet("Laba Rugi: gunakan filter per cabang untuk analisis profitabilitas per lokasi"),
      bullet("Arus Kas: pastikan periode dimulai dari awal tahun untuk hasil akumulasi yang benar"),
      space(),

      h1("7. Pencarian Global"),
      p("Gunakan fitur pencarian di pojok kanan atas untuk menemukan dengan cepat:"),
      bullet("Jurnal umum berdasarkan nomor voucher, referensi, atau keterangan"),
      bullet("Akun berdasarkan kode atau nama akun"),
      bullet("Transaksi kas/bank berdasarkan referensi atau keterangan"),
      p("Ketik kata kunci dan tekan Enter. Hasil pencarian ditampilkan per kategori."),
      space(),

      pageBreak(),

      h1("8. Pertanyaan Umum (FAQ)"),
      space(),
      h3("Jurnal saya tidak bisa diedit, kenapa?"),
      p("Jurnal dengan status Posted atau Cancelled tidak dapat diubah. Jika perlu koreksi pada jurnal Posted, buat jurnal pembalik (reverse) atau jurnal koreksi baru."),
      space(),
      h3("Kenapa saya tidak bisa approve jurnal yang saya buat sendiri?"),
      p("Ini adalah kontrol internal (4-eyes principle) yang disengaja untuk mencegah kecurangan. Jurnal yang Anda buat harus disetujui oleh pengguna lain yang memiliki hak approve."),
      space(),
      h3("Apa bedanya Lock dan Close pada periode akuntansi?"),
      bullet("Lock: transaksi baru tidak bisa masuk ke periode ini, namun periode masih bisa di-unlock"),
      bullet("Close: periode ditutup permanen setelah closing entry. Tidak bisa dibuka kembali."),
      space(),
      h3("Saldo laporan saya tidak balance, apa yang harus dilakukan?"),
      bullet("Pastikan semua jurnal sudah diposting (bukan masih draft/submitted)"),
      bullet("Periksa Neraca Saldo untuk menemukan akun yang tidak balance"),
      bullet("Hubungi administrator jika masalah berlanjut"),
      space(),
      h3("Bagaimana cara backup data?"),
      p("Backup hanya dapat dilakukan oleh administrator dengan akses backup.manage. Masuk ke menu Backup & Restore, klik Download Backup. File akan terunduh dalam format terenkripsi."),
      space(),
    ],
  }],
});

// ═══════════════════════════════════════════════════════════════════
// DOKUMEN 3: DOKUMENTASI TEKNIS
// ═══════════════════════════════════════════════════════════════════
const techDoc = new Document({
  styles, numbering,
  sections: [{
    properties: { page: pageProps },
    children: [
      new Paragraph({
        spacing: { before: 1440, after: 280 },
        children: [new TextRun({ text: "DOKUMENTASI TEKNIS", font: "Calibri", size: 52, bold: true, color: NAVY })],
      }),
      new Paragraph({
        spacing: { after: 200 },
        children: [new TextRun({ text: "Accounting GL — Panduan Developer & Arsitektur", font: "Calibri", size: 28, color: TEAL, italic: true })],
      }),
      new Paragraph({
        spacing: { after: 800 },
        children: [new TextRun({ text: "Versi 1.0  ·  Internal — Tim Teknis", font: "Calibri", size: 22, color: GRAY })],
      }),
      new Paragraph({
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: TEAL } },
        spacing: { after: 480 },
        children: [],
      }),

      h1("1. Arsitektur Sistem"),
      space(),
      h2("1.1 Stack Teknologi"),
      makeTable(
        ["Layer", "Teknologi", "Versi / Keterangan"],
        [
          ["Backend Framework", "Laravel (PHP)", "Versi 11+, MVC Pattern"],
          ["Frontend Template", "Blade + Bootstrap 5", "Alpine.js untuk interaksi ringan"],
          ["Frontend Build", "React JSX + Vite", "Komponen interaktif (form jurnal)"],
          ["Database (Produksi)", "MySQL / MariaDB", "Rekomendasi: MySQL 8.0+"],
          ["Database (Dev)", "SQLite", "File-based, tidak butuh server DB"],
          ["Authentication", "Laravel Sanctum / Session", "Session-based untuk web"],
          ["MFA", "pragmarx/google2fa", "TOTP RFC 6238"],
          ["PDF Export", "SimplePdf (custom)", "Murni PHP, tanpa dependensi eksternal"],
          ["Backup", "Symfony Process + mysqldump", "Enkripsi Laravel Crypt"],
        ]
      ),
      space(),

      h2("1.2 Struktur Direktori Utama"),
      p("app/Http/Controllers/  — 24 controller untuk semua modul", { font: "Courier New", size: 22 }),
      p("app/Http/Middleware/   — EnsurePermission, EnsureRole, SecurityHeaders", { font: "Courier New", size: 22 }),
      p("app/Models/            — 18 Eloquent model", { font: "Courier New", size: 22 }),
      p("app/Policies/          — JournalEntryPolicy (Gate integration)", { font: "Courier New", size: 22 }),
      p("app/Support/           — CashBankMutation, SimplePdf, TransactionAnomaly", { font: "Courier New", size: 22 }),
      p("app/Services/          — TaxQueryService", { font: "Courier New", size: 22 }),
      p("routes/web.php         — Semua route web (auth + permission middleware)", { font: "Courier New", size: 22 }),
      space(),

      pageBreak(),

      h1("2. Sistem Routing"),
      space(),
      h2("2.1 Pola Middleware"),
      p("Semua route terproteksi menggunakan dua lapisan middleware:"),
      bullet("auth — Memastikan pengguna sudah login (Laravel built-in)"),
      bullet("permission:{izin} — Memastikan pengguna memiliki izin yang diperlukan"),
      p("Contoh route:", { bold: true }),
      p("Route::resource('journals', JournalEntryController::class)", { font: "Courier New", size: 21 }),
      p("    ->middleware('permission:journal.view,journal.create')", { font: "Courier New", size: 21 }),
      p("    ->whereNumber('journal');", { font: "Courier New", size: 21 }),
      space(),
      p("Middleware permission menggunakan logika OR: cukup salah satu izin yang terpenuhi untuk akses route. Kontrol lebih spesifik per method dilakukan di controller."),
      space(),

      h2("2.2 Daftar Route Utama"),
      makeTable(
        ["Method", "Path", "Aksi", "Permission"],
        [
          ["GET", "/dashboard", "Dashboard utama", "dashboard.view"],
          ["GET/POST", "/journals", "CRUD Jurnal", "journal.view, journal.create"],
          ["POST", "/journals/{id}/submit", "Submit jurnal", "journal.create"],
          ["POST", "/journals/{id}/approve", "Approve jurnal", "journal.approve"],
          ["POST", "/journals/{id}/post", "Posting jurnal", "journal.post"],
          ["POST", "/journals/{id}/cancel", "Batalkan jurnal", "journal.cancel"],
          ["GET/POST", "/cash-bank-transactions", "Transaksi Kas/Bank", "cash_transaction.view"],
          ["GET", "/reports/ledger", "Buku Besar", "report.view"],
          ["GET", "/tax-reports/summary", "Laporan Pajak", "tax_report.view"],
          ["POST", "/backups/download", "Download backup", "backup.manage"],
        ]
      ),
      space(),

      pageBreak(),

      h1("3. Sistem Permission & RBAC"),
      space(),
      h2("3.1 Arsitektur Permission"),
      p("Permission disimpan dalam tabel permissions dan dihubungkan ke role melalui tabel pivot role_permission. Model Role juga mendukung permission bawaan (default) yang di-hardcode sebagai fallback."),
      space(),
      h2("3.2 Daftar Permission"),
      makeTable(
        ["Modul", "Permission Code", "Deskripsi"],
        [
          ["Dashboard", "dashboard.view", "Akses halaman dashboard"],
          ["Perusahaan", "company.manage", "Kelola data perusahaan"],
          ["Cabang", "branch.manage", "Kelola data cabang"],
          ["Periode", "period.manage", "Lock/unlock/close periode"],
          ["Akun", "account.view", "Lihat chart of account"],
          ["Akun", "account.manage", "Edit chart of account"],
          ["Jurnal", "journal.view", "Lihat daftar jurnal"],
          ["Jurnal", "journal.create", "Input dan edit jurnal"],
          ["Jurnal", "journal.approve", "Approve/reject jurnal"],
          ["Jurnal", "journal.post", "Posting jurnal ke GL"],
          ["Jurnal", "journal.cancel", "Batalkan jurnal"],
          ["Jurnal", "journal.delete", "Hapus jurnal draft"],
          ["Kas/Bank", "cash_bank.view", "Lihat rekening kas/bank"],
          ["Kas/Bank", "cash_bank.manage", "Kelola rekening kas/bank"],
          ["Transaksi", "cash_transaction.view", "Lihat mutasi kas/bank"],
          ["Transaksi", "cash_transaction.create", "Input transaksi kas/bank"],
          ["Rekonsiliasi", "bank_reconciliation.view", "Lihat rekonsiliasi bank"],
          ["Rekonsiliasi", "bank_reconciliation.create", "Buat rekonsiliasi bank"],
          ["Closing", "closing.create", "Buat closing entry"],
          ["Laporan", "report.view", "Akses semua laporan keuangan"],
          ["Pajak", "tax_report.view", "Akses laporan pajak"],
          ["Audit", "audit_trail.view", "Lihat audit trail"],
          ["User", "user.manage", "Kelola pengguna"],
          ["Role", "role.manage", "Kelola role dan permission"],
          ["Backup", "backup.manage", "Backup dan restore database"],
        ]
      ),
      space(),

      pageBreak(),

      h1("4. Model Database"),
      space(),
      h2("4.1 Model Utama"),
      makeTable(
        ["Model", "Tabel", "Relasi Penting"],
        [
          ["User", "users", "belongsTo Role, Company"],
          ["Role", "roles", "hasMany User; belongsToMany Permission"],
          ["Permission", "permissions", "belongsToMany Role"],
          ["Company", "companies", "hasMany User, Branch, Account"],
          ["Branch", "branches", "belongsTo Company"],
          ["Account", "accounts", "belongsTo Company"],
          ["FiscalPeriod", "fiscal_periods", "belongsTo Company"],
          ["JournalEntry", "journal_entries", "hasMany JournalDetail; belongsTo Company, Branch, User"],
          ["JournalDetail", "journal_details", "belongsTo JournalEntry, Account"],
          ["CashBank", "cash_banks", "belongsTo Company, Branch, Account"],
          ["CashBankTransaction", "cash_bank_transactions", "belongsTo CashBank, JournalEntry"],
          ["BankReconciliation", "bank_reconciliations", "belongsTo Company, CashBank"],
          ["AuditLog", "audit_logs", "belongsTo User, Company"],
        ]
      ),
      space(),

      h2("4.2 Status Jurnal"),
      makeTable(
        ["Status", "Nilai DB", "Transisi yang Diizinkan"],
        [
          ["Draft", "draft", "→ submitted, cancelled"],
          ["Submitted", "submitted", "→ approved, rejected, cancelled"],
          ["Approved", "approved", "→ posted"],
          ["Posted", "posted", "(terminal — tidak bisa berubah)"],
          ["Rejected", "rejected", "→ submitted (setelah revisi), cancelled"],
          ["Cancelled", "cancelled", "(terminal — tidak bisa berubah)"],
        ]
      ),
      space(),

      pageBreak(),

      h1("5. Keamanan — Detail Teknis"),
      space(),
      h2("5.1 Middleware SecurityHeaders"),
      p("Middleware SecurityHeaders diterapkan ke semua response dan menyertakan header berikut:"),
      bullet("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net"),
      bullet("X-Frame-Options: DENY"),
      bullet("X-Content-Type-Options: nosniff"),
      bullet("Referrer-Policy: strict-origin-when-cross-origin"),
      bullet("Permissions-Policy: camera=(), microphone=(), geolocation=()"),
      bullet("Strict-Transport-Security: max-age=31536000; includeSubDomains (hanya produksi/HTTPS)"),
      space(),

      h2("5.2 MFA — Implementasi TOTP"),
      p("MFA menggunakan library pragmarx/google2fa dengan alur berikut:"),
      numbered("Saat login berhasil dan google2fa_enabled=true: session menyimpan mfa.user_id, user di-logout"),
      numbered("User diarahkan ke /mfa/challenge (accessible hanya jika session mfa.user_id ada)"),
      numbered("User memasukkan kode 6 digit; diverifikasi oleh google2fa->verifyKey()"),
      numbered("Jika valid: Auth::login() dipanggil, session MFA dihapus, session di-regenerate"),
      p("Secret TOTP disimpan dalam kolom google2fa_secret yang di-cast encrypted (enkripsi AES-256 menggunakan APP_KEY)."),
      space(),

      h2("5.3 Audit Log"),
      p("AuditLog::record() dipanggil di setiap aksi kritis. Record menyimpan:"),
      bullet("action: nama aksi (create, update, delete, login, export, dll.)"),
      bullet("model_type: jenis entitas (General Journal, Authentication, dll.)"),
      bullet("old_values / new_values: snapshot data sebelum dan sesudah perubahan (JSON)"),
      bullet("user_id, company_id, branch_id, ip_address, user_agent"),
      space(),

      h2("5.4 Isu Keamanan yang Perlu Diperbaiki"),
      makeTable(
        ["Isu", "Prioritas", "Solusi"],
        [
          ["google2fa_secret tidak ada di $hidden User model", "Tinggi", "Tambahkan ke array $hidden di User.php"],
          ["Restore backup menerima ekstensi .txt", "Tinggi", "Hapus 'txt' dari whitelist ekstensi restore"],
          ["Session MFA tidak dihapus saat gagal berulang", "Sedang", "Hapus session setelah N kali gagal atau redirect ke login"],
          ["Permission middleware OR — terlalu permisif di resource route", "Sedang", "Tambahkan cek permission spesifik per method di controller"],
        ]
      ),
      space(),

      pageBreak(),

      h1("6. Deployment & Konfigurasi"),
      space(),
      h2("6.1 Variabel Environment Kritis"),
      makeTable(
        ["Variabel", "Wajib", "Keterangan"],
        [
          ["APP_KEY", "Ya", "32 karakter random — kunci enkripsi backup dan kolom encrypted"],
          ["APP_ENV", "Ya", "production / local — mengaktifkan HSTS dan URL force HTTPS"],
          ["DB_CONNECTION", "Ya", "mysql atau sqlite"],
          ["DB_DUMP_BINARY", "Tidak", "Path ke mysqldump jika berbeda dari default"],
          ["DB_MYSQL_BINARY", "Tidak", "Path ke mysql CLI jika berbeda dari default"],
        ]
      ),
      space(),
      p("Catatan: Sistem akan melempar RuntimeException jika APP_KEY kosong saat APP_ENV=production (didefinisikan di AppServiceProvider)."),
      space(),

      h2("6.2 Konfigurasi Produksi yang Direkomendasikan"),
      bullet("Gunakan HTTPS — HSTS diaktifkan otomatis jika request->isSecure() atau APP_ENV=production"),
      bullet("Set APP_ENV=production — mengaktifkan URL::forceScheme('https')"),
      bullet("Backup APP_KEY — kehilangan APP_KEY membuat semua backup terenkripsi dan kolom encrypted tidak terbaca"),
      bullet("Database MySQL 8.0+ — SQLite tidak direkomendasikan untuk produksi multi-user"),
      bullet("Queue worker — belum digunakan saat ini, siap untuk notifikasi email di masa depan"),
      space(),

      h2("6.3 Cara Menjalankan Migrasi"),
      p("php artisan migrate          # Jalankan semua migrasi baru", { font: "Courier New", size: 21 }),
      p("php artisan db:seed          # Seed data awal (role, permission, akun)", { font: "Courier New", size: 21 }),
      p("php artisan migrate:fresh --seed  # Reset & seed ulang (HATI-HATI: hapus semua data)", { font: "Courier New", size: 21 }),
      space(),

      new Paragraph({
        border: { top: { style: BorderStyle.SINGLE, size: 4, color: TEAL } },
        spacing: { before: 480, after: 200 },
        children: [new TextRun({ text: "Accounting GL · Dokumentasi Teknis · Versi 1.0 · Mei 2026 · INTERNAL", font: "Calibri", size: 20, color: GRAY, italic: true })],
      }),
    ],
  }],
});

// ─── WRITE ALL FILES ─────────────────────────────────────────────
Promise.all([
  Packer.toBuffer(proposalDoc).then(buf => fs.writeFileSync(path.join(outputDir, "Accounting_GL_Proposal_Eksekutif.docx"), buf)),
  Packer.toBuffer(userManualDoc).then(buf => fs.writeFileSync(path.join(outputDir, "Accounting_GL_Panduan_Pengguna.docx"), buf)),
  Packer.toBuffer(techDoc).then(buf => fs.writeFileSync(path.join(outputDir, "Accounting_GL_Dokumentasi_Teknis.docx"), buf)),
]).then(() => console.log("All 3 docs done")).catch(e => console.error(e));
