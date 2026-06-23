from __future__ import annotations

import html
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "docs" / "presentasi-accounting-gl.pptx"

SLIDES = [
    (
        "Accounting GL",
        [
            "Sistem akuntansi internal untuk transaksi, approval, laporan keuangan, audit trail, dan keamanan data perusahaan.",
            "Fokus: operasional multi-cabang, kontrol role, dan laporan periodik.",
        ],
    ),
    (
        "Masalah Bisnis",
        [
            "Transaksi tersebar di banyak cabang.",
            "Pencatatan manual rawan salah dan terlambat.",
            "Approval tidak selalu terdokumentasi.",
            "Laporan membutuhkan rekap manual.",
            "Data keuangan perlu dibatasi per role dan perusahaan.",
        ],
    ),
    (
        "Tujuan Aplikasi",
        [
            "Menyatukan pencatatan transaksi.",
            "Mengontrol proses approval.",
            "Membuat laporan keuangan lebih cepat.",
            "Menjaga audit trail.",
            "Mengurangi risiko kebocoran data.",
        ],
    ),
    (
        "Fitur Utama Saat Ini",
        [
            "Dashboard keuangan.",
            "Master perusahaan, cabang, periode, dan akun.",
            "Kas dan bank.",
            "Jurnal umum dan workflow approval.",
            "Rekonsiliasi bank dan closing entry.",
            "Laporan, user, role, backup, dan MFA.",
        ],
    ),
    (
        "Dashboard",
        [
            "Pendapatan, beban, dan laba/rugi bulan ini.",
            "Pending approval dan jurnal posted.",
            "Grafik performa bulanan.",
            "Shortcut export laporan.",
            "Transaksi terakhir untuk monitoring cepat.",
        ],
    ),
    (
        "Alur Kas dan Bank",
        [
            "User input penerimaan, pengeluaran, atau transfer.",
            "Sistem validasi cabang, akun, periode, dan nominal.",
            "Sistem membuat jurnal terkait.",
            "Transaksi masuk laporan setelah diposting.",
        ],
    ),
    (
        "Alur Jurnal",
        [
            "Draft: transaksi masih bisa diedit.",
            "Submitted: menunggu approval.",
            "Approved: siap diposting.",
            "Posted: masuk laporan dan terkunci.",
            "Rejected atau cancelled: tidak masuk laporan final.",
        ],
    ),
    (
        "Laporan Keuangan",
        [
            "Buku Besar.",
            "Neraca Saldo.",
            "Laba Rugi.",
            "Neraca.",
            "Arus Kas.",
            "Arus Kas Tidak Langsung.",
            "Audit Trail.",
        ],
    ),
    (
        "Keamanan",
        [
            "Rate limit login dan MFA.",
            "Session regeneration dan secure cookie.",
            "CSRF protection dan authorization policy.",
            "Permission per role.",
            "Security headers.",
            "Backup terenkripsi dan audit log.",
        ],
    ),
    (
        "Struktur Kantor Target",
        [
            "3 kasir, masing-masing memegang 3 cabang.",
            "3 staff pajak, masing-masing memegang 3 cabang.",
            "1 manager pajak.",
            "Aplikasi sudah punya company, branch, user, role, dan permission.",
            "Tambahan penting: akses user per cabang.",
        ],
    ),
    (
        "Tambahan Wajib",
        [
            "Assignment user ke banyak cabang.",
            "Filter cabang di seluruh transaksi dan laporan.",
            "Role cashier, tax_staff, dan tax_manager.",
            "Dashboard per role.",
            "Approval pajak dan laporan pajak.",
        ],
    ),
    (
        "Modul Pajak",
        [
            "Belum tersedia lengkap saat ini.",
            "Perlu master jenis pajak dan tarif.",
            "Perlu nomor faktur pajak dan NPWP lawan transaksi.",
            "Perlu PPN Masukan, PPN Keluaran, dan PPh.",
            "Perlu rekap per cabang/periode dan export.",
        ],
    ),
    (
        "User Non-Akuntansi",
        [
            "Menu berbasis aktivitas bisnis.",
            "Template transaksi.",
            "Form sederhana tanpa debit/kredit.",
            "Pesan error yang mudah dipahami.",
            "Dashboard sesuai pekerjaan.",
        ],
    ),
    (
        "Roadmap",
        [
            "1. Akses user per cabang.",
            "2. Dashboard kasir dan pajak.",
            "3. Modul pajak dasar.",
            "4. Approval pajak.",
            "5. Export laporan pajak.",
            "6. Template transaksi sederhana.",
        ],
    ),
    (
        "Kesimpulan",
        [
            "Accounting GL sudah siap sebagai fondasi sistem akuntansi internal.",
            "Untuk multi-cabang dan tim pajak, prioritas berikutnya adalah akses per cabang, dashboard role-based, dan modul pajak.",
        ],
    ),
]


def xml_escape(value: str) -> str:
    return html.escape(value, quote=True)


def content_types() -> str:
    overrides = "\n".join(
        f'<Override PartName="/ppt/slides/slide{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'
        for i in range(1, len(SLIDES) + 1)
    )

    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
  <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>
  <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>
  <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  {overrides}
</Types>'''


def root_rels() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>'''


def presentation() -> str:
    slide_ids = "\n".join(
        f'<p:sldId id="{255 + i}" r:id="rId{i}"/>' for i in range(1, len(SLIDES) + 1)
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId{len(SLIDES) + 1}"/></p:sldMasterIdLst>
  <p:sldIdLst>{slide_ids}</p:sldIdLst>
  <p:sldSz cx="12192000" cy="6858000" type="wide"/>
  <p:notesSz cx="6858000" cy="9144000"/>
</p:presentation>'''


def presentation_rels() -> str:
    slide_rels = "\n".join(
        f'<Relationship Id="rId{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide{i}.xml"/>'
        for i in range(1, len(SLIDES) + 1)
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  {slide_rels}
  <Relationship Id="rId{len(SLIDES) + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>
  <Relationship Id="rId{len(SLIDES) + 2}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>'''


def slide(title: str, bullets: list[str], index: int) -> str:
    title = xml_escape(title)
    bullet_xml = "\n".join(
        f'''<a:p>
          <a:pPr marL="342900" indent="-228600"><a:buChar char="•"/></a:pPr>
          <a:r><a:rPr lang="id-ID" sz="2400"/><a:t>{xml_escape(item)}</a:t></a:r>
        </a:p>'''
        for item in bullets
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld>
    <p:bg><p:bgPr><a:solidFill><a:srgbClr val="F8FAFC"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>
        <p:spPr><a:xfrm><a:off x="685800" y="487680"/><a:ext cx="10668000" cy="914400"/></a:xfrm></p:spPr>
        <p:txBody>
          <a:bodyPr/><a:lstStyle/>
          <a:p><a:r><a:rPr lang="id-ID" sz="4000" b="1"><a:solidFill><a:srgbClr val="0F172A"/></a:solidFill></a:rPr><a:t>{title}</a:t></a:r></a:p>
        </p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="3" name="Content"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>
        <p:spPr><a:xfrm><a:off x="914400" y="1645920"/><a:ext cx="10363200" cy="4267200"/></a:xfrm></p:spPr>
        <p:txBody>
          <a:bodyPr/><a:lstStyle/>
          {bullet_xml}
        </p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="4" name="Footer"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
        <p:spPr><a:xfrm><a:off x="914400" y="6248400"/><a:ext cx="10000000" cy="365760"/></a:xfrm></p:spPr>
        <p:txBody>
          <a:bodyPr/><a:lstStyle/>
          <a:p><a:r><a:rPr lang="id-ID" sz="1200"><a:solidFill><a:srgbClr val="64748B"/></a:solidFill></a:rPr><a:t>Accounting GL - Slide {index}</a:t></a:r></a:p>
        </p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sld>'''


def slide_rels() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
</Relationships>'''


def slide_master() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld>
  <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>
  <p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>
</p:sldMaster>'''


def slide_master_rels() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>
</Relationships>'''


def slide_layout() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">
  <p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld>
  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sldLayout>'''


def slide_layout_rels() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
</Relationships>'''


def theme() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Accounting GL">
  <a:themeElements>
    <a:clrScheme name="Accounting GL">
      <a:dk1><a:srgbClr val="0F172A"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>
      <a:dk2><a:srgbClr val="334155"/></a:dk2><a:lt2><a:srgbClr val="F8FAFC"/></a:lt2>
      <a:accent1><a:srgbClr val="0F766E"/></a:accent1><a:accent2><a:srgbClr val="2563EB"/></a:accent2>
      <a:accent3><a:srgbClr val="B91C1C"/></a:accent3><a:accent4><a:srgbClr val="CA8A04"/></a:accent4>
      <a:accent5><a:srgbClr val="475569"/></a:accent5><a:accent6><a:srgbClr val="16A34A"/></a:accent6>
      <a:hlink><a:srgbClr val="2563EB"/></a:hlink><a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Plus Jakarta Sans"><a:majorFont><a:latin typeface="Plus Jakarta Sans"/></a:majorFont><a:minorFont><a:latin typeface="Plus Jakarta Sans"/></a:minorFont></a:fontScheme>
    <a:fmtScheme name="Accounting GL"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme>
  </a:themeElements>
</a:theme>'''


def core_props() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Presentasi Accounting GL</dc:title>
  <dc:subject>Tutorial dan roadmap aplikasi akuntansi</dc:subject>
  <dc:creator>Accounting GL</dc:creator>
  <cp:lastModifiedBy>Accounting GL</cp:lastModifiedBy>
</cp:coreProperties>'''


def app_props() -> str:
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Accounting GL</Application>
  <PresentationFormat>Widescreen</PresentationFormat>
  <Slides>{len(SLIDES)}</Slides>
</Properties>'''


def build() -> None:
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(OUTPUT, "w", compression=zipfile.ZIP_DEFLATED) as pptx:
        pptx.writestr("[Content_Types].xml", content_types())
        pptx.writestr("_rels/.rels", root_rels())
        pptx.writestr("ppt/presentation.xml", presentation())
        pptx.writestr("ppt/_rels/presentation.xml.rels", presentation_rels())
        pptx.writestr("ppt/slideMasters/slideMaster1.xml", slide_master())
        pptx.writestr("ppt/slideMasters/_rels/slideMaster1.xml.rels", slide_master_rels())
        pptx.writestr("ppt/slideLayouts/slideLayout1.xml", slide_layout())
        pptx.writestr("ppt/slideLayouts/_rels/slideLayout1.xml.rels", slide_layout_rels())
        pptx.writestr("ppt/theme/theme1.xml", theme())
        pptx.writestr("docProps/core.xml", core_props())
        pptx.writestr("docProps/app.xml", app_props())

        for index, (title, bullets) in enumerate(SLIDES, start=1):
            pptx.writestr(f"ppt/slides/slide{index}.xml", slide(title, bullets, index))
            pptx.writestr(f"ppt/slides/_rels/slide{index}.xml.rels", slide_rels())


if __name__ == "__main__":
    build()
    print(OUTPUT)
