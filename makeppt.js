const pptxgen = (await import("pptxgenjs")).default;
const path = await import("node:path");
const { fileURLToPath } = await import("node:url");

const outputDir = path.dirname(fileURLToPath(import.meta.url));

const pres = new pptxgen();
pres.layout = "LAYOUT_16x9";
pres.title = "Accounting GL - Sistem Akuntansi Perusahaan";
pres.author = "Accounting GL Team";

// ─── PALETTE ───────────────────────────────────────────────
const C = {
  navy:    "0F2D55",
  teal:    "0D7E6A",
  tealLt:  "14A88A",
  mint:    "D6F0EB",
  white:   "FFFFFF",
  offWhite:"F4F7FA",
  gray:    "64748B",
  grayLt:  "E2EAF4",
  black:   "1E293B",
  amber:   "D97706",
  red:     "DC2626",
  green:   "16A34A",
};

const makeShadow = () => ({ type:"outer", blur:8, offset:3, angle:135, color:"000000", opacity:0.12 });

// ─── HELPERS ───────────────────────────────────────────────
function titleSlide(slide) {
  slide.background = { color: C.navy };
}

function sectionBg(slide) {
  slide.background = { color: C.navy };
}

function contentBg(slide) {
  slide.background = { color: C.offWhite };
}

function addNavyCard(slide, x, y, w, h) {
  slide.addShape(pres.shapes.RECTANGLE, {
    x, y, w, h,
    fill: { color: C.white },
    line: { color: C.grayLt, width: 0.5 },
    shadow: makeShadow(),
  });
}

function addAccentLine(slide, x, y, h) {
  slide.addShape(pres.shapes.RECTANGLE, {
    x, y, w: 0.07, h,
    fill: { color: C.teal },
    line: { color: C.teal, width: 0 },
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 1 — COVER
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  titleSlide(s);

  // decorative teal block kiri
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0, y: 0, w: 0.35, h: 5.625,
    fill: { color: C.teal }, line: { color: C.teal, width: 0 }
  });

  // teal accent bawah
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.35, y: 4.8, w: 9.65, h: 0.825,
    fill: { color: C.tealLt, transparency: 85 }, line: { color: C.tealLt, width: 0 }
  });

  s.addText("ACCOUNTING GL", {
    x: 0.7, y: 1.1, w: 8.6, h: 0.7,
    fontSize: 38, bold: true, color: C.white,
    fontFace: "Calibri", charSpacing: 4,
  });

  s.addText("Sistem Informasi Akuntansi Perusahaan", {
    x: 0.7, y: 1.85, w: 8.6, h: 0.5,
    fontSize: 18, color: C.mint, fontFace: "Calibri",
  });

  s.addShape(pres.shapes.LINE, {
    x: 0.7, y: 2.45, w: 3.5, h: 0,
    line: { color: C.tealLt, width: 1.5 }
  });

  s.addText([
    { text: "Multi-perusahaan  ·  Multi-cabang  ·  Audit lengkap", options: { color: C.grayLt, fontSize: 13 } },
  ], { x: 0.7, y: 2.7, w: 8, h: 0.4, fontFace: "Calibri" });

  s.addText("Presentasi Produk & Teknis · 2026", {
    x: 0.7, y: 4.95, w: 8, h: 0.35,
    fontSize: 11, color: C.mint, fontFace: "Calibri", italic: true,
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 2 — AGENDA
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Agenda", {
    x: 0.6, y: 0.35, w: 8.8, h: 0.65,
    fontSize: 30, bold: true, color: C.navy, fontFace: "Calibri",
  });

  const items = [
    ["01", "Gambaran Umum Sistem", "Apa itu Accounting GL, tujuan, dan cakupan"],
    ["02", "Arsitektur & Teknologi", "Stack teknis, middleware, keamanan"],
    ["03", "Fitur Utama", "Transaksi, jurnal, kas/bank, rekonsiliasi"],
    ["04", "Modul Laporan & Pajak", "Laporan keuangan, pajak PPN/PPh"],
    ["05", "Manajemen & Kontrol", "Role, periode, backup, audit trail"],
    ["06", "Roadmap & Rekomendasi", "Fitur yang akan dikembangkan"],
  ];

  items.forEach(([num, title, sub], i) => {
    const col = i < 3 ? 0 : 1;
    const row = i % 3;
    const x = col === 0 ? 0.6 : 5.35;
    const y = 1.15 + row * 1.35;

    addNavyCard(s, x, y, 4.55, 1.1);
    addAccentLine(s, x, y, 1.1);

    s.addText(num, {
      x: x + 0.18, y: y + 0.08, w: 0.55, h: 0.4,
      fontSize: 22, bold: true, color: C.teal, fontFace: "Calibri", margin: 0,
    });
    s.addText(title, {
      x: x + 0.18, y: y + 0.47, w: 4.1, h: 0.32,
      fontSize: 13, bold: true, color: C.black, fontFace: "Calibri", margin: 0,
    });
    s.addText(sub, {
      x: x + 0.18, y: y + 0.76, w: 4.1, h: 0.28,
      fontSize: 10, color: C.gray, fontFace: "Calibri", margin: 0,
    });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 3 — SECTION DIVIDER: GAMBARAN UMUM
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("01", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Gambaran Umum Sistem", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Apa itu Accounting GL, tujuan, dan cakupan fitur", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 4 — APA ITU ACCOUNTING GL
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Apa itu Accounting GL?", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  // Deskripsi kiri
  addNavyCard(s, 0.6, 1.15, 5.6, 3.9);
  addAccentLine(s, 0.6, 1.15, 3.9);

  s.addText("Accounting GL adalah sistem informasi akuntansi berbasis web yang dirancang untuk perusahaan multi-cabang. Sistem ini mengotomasi seluruh siklus akuntansi — dari pencatatan jurnal, pengelolaan kas & bank, hingga pelaporan keuangan standar.", {
    x:0.82, y:1.3, w:5.2, h:1.35,
    fontSize:13, color:C.black, fontFace:"Calibri", margin:0,
  });

  const points = [
    "Workflow approval jurnal 4 tahap (Draft → Submit → Approve → Post)",
    "Multi-perusahaan & multi-cabang dalam satu platform",
    "Laporan keuangan lengkap: Neraca, L/R, Arus Kas",
    "Dashboard pajak khusus (PPN, PPh 21/23/Final)",
    "Audit trail & deteksi anomali transaksi otomatis",
    "Backup terenkripsi & pemulihan data",
  ];

  points.forEach((pt, i) => {
    s.addShape(pres.shapes.OVAL, { x:0.82, y:2.82+i*0.38, w:0.18, h:0.18, fill:{color:C.teal}, line:{color:C.teal,width:0} });
    s.addText(pt, { x:1.08, y:2.78+i*0.38, w:5.0, h:0.3, fontSize:12, color:C.black, fontFace:"Calibri", margin:0 });
  });

  // Stats kanan
  const stats = [["7", "Role\nPengguna"], ["6+", "Jenis\nLaporan"], ["4", "Tahap\nApproval"], ["3", "Jenis\nPajak"]];
  stats.forEach(([num, lbl], i) => {
    const r = i < 2 ? 0 : 1;
    const c = i % 2;
    const x = 6.5 + c * 1.85;
    const y = 1.15 + r * 2.0;
    addNavyCard(s, x, y, 1.65, 1.7);
    s.addText(num, { x, y:y+0.15, w:1.65, h:0.85, fontSize:42, bold:true, color:C.teal, align:"center", fontFace:"Calibri", margin:0 });
    s.addText(lbl, { x, y:y+0.95, w:1.65, h:0.55, fontSize:11, color:C.gray, align:"center", fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 5 — SECTION: ARSITEKTUR
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("02", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Arsitektur & Teknologi", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Stack teknis, middleware keamanan, dan pola desain", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 6 — TECH STACK
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Stack Teknologi", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const layers = [
    { label:"Frontend", color:C.teal, items:["Blade Templates (Laravel)", "Alpine.js / React JSX", "Bootstrap 5 + Custom CSS", "Chart.js untuk visualisasi data"] },
    { label:"Backend", color:C.navy, items:["Laravel (PHP) — MVC Pattern", "Eloquent ORM", "Laravel Policies & Gates", "Custom Middleware (Permission, Security Headers)"] },
    { label:"Database", color:C.amber, items:["MySQL / MariaDB (produksi)", "SQLite (development)", "Migration-based schema", "Soft deletes & audit columns"] },
    { label:"Keamanan", color:C.red, items:["MFA (Google 2FA / TOTP)", "CSP Headers, HSTS, X-Frame", "Throttle login (5x/menit)", "Enkripsi backup (APP_KEY)"] },
  ];

  layers.forEach((layer, i) => {
    const x = 0.3 + i * 2.38;
    addNavyCard(s, x, 1.15, 2.2, 3.9);

    s.addShape(pres.shapes.RECTANGLE, {
      x, y:1.15, w:2.2, h:0.5,
      fill:{color:layer.color}, line:{color:layer.color,width:0},
    });
    s.addText(layer.label, {
      x, y:1.15, w:2.2, h:0.5,
      fontSize:13, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0,
    });

    layer.items.forEach((item, j) => {
      s.addShape(pres.shapes.RECTANGLE, { x:x+0.15, y:1.82+j*0.72, w:0.07, h:0.22, fill:{color:layer.color}, line:{color:layer.color,width:0} });
      s.addText(item, {
        x:x+0.3, y:1.78+j*0.72, w:1.8, h:0.35,
        fontSize:10.5, color:C.black, fontFace:"Calibri", margin:0,
      });
    });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 7 — KEAMANAN & MIDDLEWARE
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Lapisan Keamanan", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  // Flow diagram
  const layers = [
    { label:"Request Masuk", sub:"HTTPS / Browser", color:C.gray },
    { label:"Security Headers", sub:"CSP · HSTS · X-Frame · Referrer", color:C.navy },
    { label:"Auth Middleware", sub:"Session · MFA · Throttle", color:C.teal },
    { label:"Permission Check", sub:"EnsurePermission · Role RBAC", color:C.teal },
    { label:"Controller", sub:"Validasi · Business Logic", color:C.navy },
    { label:"Audit Log", sub:"Setiap aksi tercatat", color:C.green },
  ];

  layers.forEach((layer, i) => {
    const x = 0.5;
    const y = 1.15 + i * 0.7;
    s.addShape(pres.shapes.RECTANGLE, { x, y, w:5.5, h:0.55, fill:{color:layer.color}, line:{color:layer.color,width:0}, shadow: makeShadow() });
    s.addText(layer.label, { x:x+0.15, y, w:2.5, h:0.55, fontSize:13, bold:true, color:C.white, valign:"middle", fontFace:"Calibri", margin:0 });
    s.addText(layer.sub, { x:x+2.8, y, w:2.7, h:0.55, fontSize:10.5, color:"D1D5DB", valign:"middle", fontFace:"Calibri", margin:0 });
    if (i < layers.length - 1) {
      s.addShape(pres.shapes.LINE, { x:1.8, y:y+0.55, w:0, h:0.15, line:{color:C.teal,width:2} });
    }
  });

  // Kanan — info keamanan
  const infos = [
    ["Throttle", "5 percobaan login per menit per IP"],
    ["MFA", "Google Authenticator TOTP (6 digit)"],
    ["Auditor", "Role read-only, write otomatis diblok"],
    ["Backup", "Enkripsi simetris dengan APP_KEY Laravel"],
    ["Password", "Min 8 karakter, mixed case + simbol"],
  ];

  s.addText("Fitur Keamanan", { x:6.3, y:1.1, w:3.4, h:0.4, fontSize:14, bold:true, color:C.navy, fontFace:"Calibri", margin:0 });

  infos.forEach(([title, desc], i) => {
    addNavyCard(s, 6.3, 1.55+i*0.77, 3.4, 0.65);
    s.addText(title, { x:6.45, y:1.57+i*0.77, w:1.0, h:0.28, fontSize:11, bold:true, color:C.teal, fontFace:"Calibri", margin:0 });
    s.addText(desc, { x:6.45, y:1.84+i*0.77, w:3.1, h:0.28, fontSize:10, color:C.gray, fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 8 — SECTION: FITUR UTAMA
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("03", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Fitur Utama", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Jurnal, Kas & Bank, Rekonsiliasi, Piutang", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 9 — WORKFLOW JURNAL
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Workflow Jurnal Umum", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  // Flow steps
  const steps = [
    { label:"Draft", color:C.gray, sub:"Input jurnal\nbaru" },
    { label:"Submitted", color:"1D4ED8", sub:"Submit untuk\npersetujuan" },
    { label:"Approved", color:C.green, sub:"Disetujui\napprover" },
    { label:"Posted", color:C.teal, sub:"Masuk ke\nlaporan GL" },
  ];

  steps.forEach((step, i) => {
    const x = 0.55 + i * 2.35;
    s.addShape(pres.shapes.RECTANGLE, {
      x, y:1.2, w:2.05, h:1.05,
      fill:{color:step.color}, line:{color:step.color,width:0},
      shadow: makeShadow(),
    });
    s.addText(step.label, { x, y:1.2, w:2.05, h:0.5, fontSize:16, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });
    s.addText(step.sub, { x, y:1.7, w:2.05, h:0.55, fontSize:10.5, color:"D1FAE5", align:"center", valign:"middle", fontFace:"Calibri", margin:0 });
    if (i < 3) {
      s.addShape(pres.shapes.LINE, { x:x+2.05, y:1.72, w:0.3, h:0, line:{color:C.teal,width:2.5} });
    }
  });

  // Cabang rejected/cancelled
  s.addShape(pres.shapes.LINE, { x:2.6, y:2.25, w:0, h:0.6, line:{color:C.red,width:1.5,dashType:"dash"} });
  s.addShape(pres.shapes.RECTANGLE, { x:1.55, y:2.85, w:2.1, h:0.55, fill:{color:C.red}, line:{color:C.red,width:0} });
  s.addText("Rejected", { x:1.55, y:2.85, w:2.1, h:0.55, fontSize:13, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });

  s.addShape(pres.shapes.LINE, { x:0.97, y:2.25, w:0, h:1.7, line:{color:"9CA3AF",width:1.5,dashType:"dash"} });
  s.addShape(pres.shapes.RECTANGLE, { x:0.28, y:3.95, w:2.1, h:0.55, fill:{color:C.gray}, line:{color:C.gray,width:0} });
  s.addText("Cancelled", { x:0.28, y:3.95, w:2.1, h:0.55, fontSize:13, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });

  // Kontrol penting
  const controls = [
    "Pembuat jurnal tidak bisa approve sendiri (4-eyes principle)",
    "Jurnal Posted tidak bisa diedit atau dihapus",
    "Setiap perubahan status tercatat di Audit Trail & Approval Log",
    "Pembatalan wajib menyertakan alasan minimal 10 karakter",
    "Nomor voucher di-generate otomatis per perusahaan & cabang",
    "Deteksi anomali transaksi otomatis (TransactionAnomaly)",
  ];

  addNavyCard(s, 5.15, 1.15, 4.6, 4.1);
  s.addText("Kontrol Internal", { x:5.3, y:1.25, w:4.3, h:0.4, fontSize:14, bold:true, color:C.teal, fontFace:"Calibri", margin:0 });

  controls.forEach((ctrl, i) => {
    s.addShape(pres.shapes.OVAL, { x:5.3, y:1.78+i*0.55, w:0.18, h:0.18, fill:{color:C.teal}, line:{color:C.teal,width:0} });
    s.addText(ctrl, { x:5.55, y:1.74+i*0.55, w:4.05, h:0.45, fontSize:11, color:C.black, fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 10 — KAS & BANK
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Modul Kas & Bank", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const types = [
    { label:"Kas Masuk", color:C.green, desc:"Penerimaan uang tunai ke kas perusahaan" },
    { label:"Bank Masuk", color:C.teal, desc:"Penerimaan pembayaran ke rekening bank" },
    { label:"Kas Keluar", color:C.amber, desc:"Pengeluaran uang tunai dari kas" },
    { label:"Transfer Bank", color:C.navy, desc:"Pemindahan dana antar rekening bank" },
  ];

  types.forEach((type, i) => {
    const col = i < 2 ? 0 : 1;
    const row = i % 2;
    const x = col === 0 ? 0.6 : 5.35;
    const y = 1.15 + row * 1.7;

    addNavyCard(s, x, y, 4.55, 1.45);
    s.addShape(pres.shapes.RECTANGLE, { x, y, w:4.55, h:0.42, fill:{color:type.color}, line:{color:type.color,width:0} });
    s.addText(type.label, { x, y, w:4.55, h:0.42, fontSize:14, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });
    s.addText(type.desc, { x:x+0.15, y:y+0.5, w:4.25, h:0.55, fontSize:12, color:C.black, fontFace:"Calibri", margin:0 });
  });

  // features bawah
  const feats = ["Reverse transaksi untuk koreksi", "Lampiran/attachment per transaksi", "Nomor voucher otomatis", "Saldo berjalan (running balance)"];
  addNavyCard(s, 0.6, 4.65, 9.3, 0.75);
  s.addText("Fitur tambahan:", { x:0.8, y:4.73, w:1.4, h:0.3, fontSize:11, bold:true, color:C.teal, fontFace:"Calibri", margin:0 });
  feats.forEach((f, i) => {
    s.addShape(pres.shapes.OVAL, { x:2.3+i*1.85, y:4.76, w:0.15, h:0.15, fill:{color:C.teal}, line:{color:C.teal,width:0} });
    s.addText(f, { x:2.5+i*1.85, y:4.72, w:1.65, h:0.3, fontSize:10.5, color:C.black, fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 11 — SECTION: LAPORAN & PAJAK
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("04", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Laporan & Dashboard Pajak", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Laporan keuangan standar + Pajak PPN/PPh", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 12 — LAPORAN KEUANGAN
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Laporan Keuangan Lengkap", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const reports = [
    { name:"Buku Besar", code:"Ledger", desc:"Rincian transaksi per akun dengan saldo berjalan" },
    { name:"Neraca Saldo", code:"Trial Balance", desc:"Ringkasan debit/kredit semua akun dalam satu periode" },
    { name:"Laba Rugi", code:"Profit & Loss", desc:"Pendapatan vs beban, menghitung laba/rugi bersih" },
    { name:"Neraca", code:"Balance Sheet", desc:"Aset, kewajiban, dan ekuitas perusahaan" },
    { name:"Arus Kas Langsung", code:"Cash Flow Direct", desc:"Penerimaan dan pengeluaran kas riil per periode" },
    { name:"Arus Kas Tidak Langsung", code:"Cash Flow Indirect", desc:"Rekonsiliasi laba ke arus kas operasional" },
  ];

  reports.forEach((r, i) => {
    const col = i % 2;
    const row = Math.floor(i / 2);
    const x = col === 0 ? 0.6 : 5.35;
    const y = 1.15 + row * 1.45;

    addNavyCard(s, x, y, 4.55, 1.2);
    addAccentLine(s, x, y, 1.2);

    s.addText(r.name, { x:x+0.22, y:y+0.1, w:3.5, h:0.35, fontSize:14, bold:true, color:C.navy, fontFace:"Calibri", margin:0 });
    s.addText(r.code, { x:x+0.22, y:y+0.42, w:2.5, h:0.25, fontSize:10, color:C.teal, italic:true, fontFace:"Calibri", margin:0 });
    s.addText(r.desc, { x:x+0.22, y:y+0.68, w:4.1, h:0.4, fontSize:10.5, color:C.gray, fontFace:"Calibri", margin:0 });
  });

  // Export badge
  addNavyCard(s, 0.6, 5.1, 9.1, 0.38);
  s.addText("Semua laporan dapat diekspor ke  PDF  dan  Excel  langsung dari browser", {
    x:0.8, y:5.15, w:8.7, h:0.28, fontSize:12, color:C.black, fontFace:"Calibri", margin:0,
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 13 — LAPORAN PAJAK
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Dashboard & Laporan Pajak", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const taxes = [
    { code:"PPN", name:"Pajak Pertambahan Nilai", color:C.teal },
    { code:"PPh 21", name:"Pajak Penghasilan Karyawan", color:C.navy },
    { code:"PPh 23", name:"Pajak Penghasilan Jasa", color:C.amber },
    { code:"PPh Final", name:"Pajak Penghasilan Final", color:C.green },
  ];

  taxes.forEach((tax, i) => {
    const x = 0.45 + i * 2.38;
    addNavyCard(s, x, 1.15, 2.1, 2.2);
    s.addShape(pres.shapes.RECTANGLE, { x, y:1.15, w:2.1, h:0.45, fill:{color:tax.color}, line:{color:tax.color,width:0} });
    s.addText(tax.code, { x, y:1.15, w:2.1, h:0.45, fontSize:16, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });
    s.addText(tax.name, { x:x+0.1, y:1.7, w:1.9, h:0.6, fontSize:11, color:C.black, align:"center", fontFace:"Calibri", margin:0 });
    s.addShape(pres.shapes.LINE, { x:x+0.2, y:2.35, w:1.7, h:0, line:{color:C.grayLt,width:0.5} });
    s.addText("Saldo hutang\nper periode", { x:x+0.1, y:2.42, w:1.9, h:0.55, fontSize:10, color:C.gray, align:"center", fontFace:"Calibri", margin:0 });
  });

  const features = [
    ["Dashboard Pajak", "Ringkasan PPN, PPh, dan laba sebelum pajak. Perbandingan vs bulan sebelumnya otomatis."],
    ["Rekonsiliasi Pajak", "Cek selisih antara hutang pajak di GL vs pembayaran aktual ke DJP. Deteksi akun pajak yang belum dikategorikan."],
    ["Laporan Summary Pajak", "Tampilkan semua akun pajak per jenis, filter per cabang dan periode, ekspor ke PDF."],
  ];

  features.forEach(([title, desc], i) => {
    addNavyCard(s, 0.45, 3.55+i*0.65, 9.1, 0.55);
    addAccentLine(s, 0.45, 3.55+i*0.65, 0.55);
    s.addText(title+":", { x:0.65, y:3.6+i*0.65, w:2.0, h:0.3, fontSize:12, bold:true, color:C.teal, fontFace:"Calibri", margin:0 });
    s.addText(desc, { x:2.75, y:3.6+i*0.65, w:6.7, h:0.3, fontSize:11, color:C.black, fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 14 — SECTION: MANAJEMEN & KONTROL
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("05", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Manajemen & Kontrol", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Role, periode, backup, dan audit trail", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 15 — RBAC & ROLE
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Role & Hak Akses (RBAC)", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const roles = [
    { name:"Super Admin", color:C.red, perms:"Semua akses penuh + kelola perusahaan" },
    { name:"Manager Internal", color:C.navy, perms:"Jurnal, Kas/Bank, Approve, Posting, Closing, User" },
    { name:"OmM", color:C.navy, perms:"Setara Manager Internal — operasional penuh" },
    { name:"Manager Pajak", color:C.teal, perms:"Jurnal, Laporan, Pajak, Approve jurnal" },
    { name:"Admin Pajak", color:C.teal, perms:"Laporan pajak, input jurnal, view kas/bank" },
    { name:"Kasir Cabang", color:C.amber, perms:"View dashboard, input kas/bank, view laporan" },
    { name:"Auditor", color:C.gray, perms:"Hanya baca — semua write otomatis diblok sistem" },
  ];

  roles.forEach((role, i) => {
    const col = i < 4 ? 0 : 1;
    const row = i < 4 ? i : i - 4;
    const x = col === 0 ? 0.6 : 5.35;
    const y = 1.15 + row * 1.05;

    addNavyCard(s, x, y, 4.55, 0.88);
    s.addShape(pres.shapes.RECTANGLE, { x, y, w:0.3, h:0.88, fill:{color:role.color}, line:{color:role.color,width:0} });
    s.addText(role.name, { x:x+0.45, y:y+0.06, w:3.9, h:0.34, fontSize:13, bold:true, color:C.black, fontFace:"Calibri", margin:0 });
    s.addText(role.perms, { x:x+0.45, y:y+0.44, w:3.9, h:0.34, fontSize:10.5, color:C.gray, fontFace:"Calibri", margin:0 });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 16 — SECTION: ROADMAP
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);
  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addText("06", { x:0.7, y:1.6, w:3, h:1.2, fontSize:72, bold:true, color:C.tealLt, fontFace:"Calibri", margin:0 });
  s.addText("Roadmap & Rekomendasi", { x:0.7, y:2.85, w:8.5, h:0.65, fontSize:28, bold:true, color:C.white, fontFace:"Calibri", margin:0 });
  s.addText("Fitur yang direncanakan untuk pengembangan berikutnya", { x:0.7, y:3.55, w:8.5, h:0.4, fontSize:15, color:C.mint, fontFace:"Calibri", margin:0 });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 17 — ROADMAP
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  contentBg(s);

  s.addText("Roadmap Pengembangan", {
    x:0.6, y:0.35, w:8.8, h:0.65,
    fontSize:28, bold:true, color:C.navy, fontFace:"Calibri",
  });

  const roadmap = [
    { phase:"Segera (Prioritas Tinggi)", color:C.red, items:["Forgot password / reset via email", "Notifikasi email saat jurnal disubmit/diapprove", "Tipe transaksi bank_out (pembayaran bank langsung)"] },
    { phase:"Jangka Menengah", color:C.amber, items:["Laporan perbandingan periode (comparative)", "Halaman profil user self-service", "Validasi closing: cek jurnal pending sebelum tutup"] },
    { phase:"Jangka Panjang", color:C.teal, items:["Modul Hutang Usaha (Accounts Payable)", "Anggaran vs Realisasi (Budgeting)", "API publik untuk integrasi ERP/POS"] },
  ];

  roadmap.forEach((phase, i) => {
    addNavyCard(s, 0.6, 1.15+i*1.45, 9.1, 1.25);
    s.addShape(pres.shapes.RECTANGLE, { x:0.6, y:1.15+i*1.45, w:9.1, h:0.38, fill:{color:phase.color}, line:{color:phase.color,width:0} });
    s.addText(phase.phase, { x:0.75, y:1.15+i*1.45, w:8.8, h:0.38, fontSize:13, bold:true, color:C.white, valign:"middle", fontFace:"Calibri", margin:0 });

    phase.items.forEach((item, j) => {
      s.addShape(pres.shapes.OVAL, { x:0.75+j*3.0, y:1.68+i*1.45, w:0.15, h:0.15, fill:{color:phase.color}, line:{color:phase.color,width:0} });
      s.addText(item, { x:0.97+j*3.0, y:1.64+i*1.45, w:2.75, h:0.55, fontSize:11, color:C.black, fontFace:"Calibri", margin:0 });
    });
  });
}

// ═══════════════════════════════════════════════════════════
// SLIDE 18 — PENUTUP
// ═══════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  sectionBg(s);

  s.addShape(pres.shapes.RECTANGLE, { x:0, y:0, w:0.35, h:5.625, fill:{color:C.teal}, line:{color:C.teal,width:0} });
  s.addShape(pres.shapes.RECTANGLE, { x:0.35, y:4.5, w:9.65, h:1.125, fill:{color:C.tealLt, transparency:80}, line:{color:C.tealLt,width:0} });

  s.addText("Accounting GL", { x:0.7, y:1.5, w:8.5, h:0.75, fontSize:40, bold:true, color:C.white, fontFace:"Calibri", charSpacing:3 });
  s.addText("Sistem akuntansi yang andal, aman, dan siap\ntumbuh bersama bisnis perusahaan Anda.", {
    x:0.7, y:2.35, w:8.3, h:0.9, fontSize:17, color:C.mint, fontFace:"Calibri",
  });

  const pillars = ["Keamanan berlapis", "Audit lengkap", "Multi-entitas", "Laporan standar"];
  pillars.forEach((p, i) => {
    s.addShape(pres.shapes.RECTANGLE, { x:0.7+i*2.3, y:3.55, w:2.0, h:0.45, fill:{color:C.teal}, line:{color:C.teal,width:0} });
    s.addText(p, { x:0.7+i*2.3, y:3.55, w:2.0, h:0.45, fontSize:12, bold:true, color:C.white, align:"center", valign:"middle", fontFace:"Calibri", margin:0 });
  });

  s.addText("Terima kasih", { x:0.7, y:4.6, w:8.5, h:0.4, fontSize:16, color:C.mint, italic:true, fontFace:"Calibri" });
}

// ─── WRITE FILE ─────────────────────────────────────────────
pres.writeFile({ fileName: path.join(outputDir, "Accounting_GL_Presentation.pptx") })
  .then(() => console.log("PPT done"))
  .catch(e => console.error(e));
