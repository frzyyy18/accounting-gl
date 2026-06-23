@extends('layouts.app', ['title' => $journal->exists ? 'Edit Jurnal' : 'Tambah Jurnal'])

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>{{ $journal->exists ? 'Edit Jurnal' : 'Input Jurnal Baru' }}</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge text-bg-light border">Debit = Kredit</span>
            <span class="badge text-bg-light border text-muted" style="font-weight:400">
                <kbd>Enter</kbd> pindah field &nbsp;·&nbsp;
                <kbd>Ctrl+D</kbd> isi selisih &nbsp;·&nbsp;
                <kbd>Ctrl+Enter</kbd> baris baru &nbsp;·&nbsp;
                <kbd>Ctrl+Del</kbd> hapus baris
            </span>
        </div>
    </div>
    <div class="p-4">
    <form method="post" enctype="multipart/form-data"
          action="{{ $journal->exists ? route('journals.update',$journal) : route('journals.store') }}"
          id="journal-form"
          data-autosave-key="journal-form:{{ $journal->exists ? 'edit:'.$journal->id : 'create:'.$selectedCompanyId }}">
        @csrf @if($journal->exists) @method('put') @endif

        @isset($duplicatedFrom)
            <div class="alert alert-info">
                Salinan dari jurnal {{ $duplicatedFrom->reference_number ?: $duplicatedFrom->journal_number }}. No. voucher akan dibuat ulang saat disimpan.
            </div>
        @endisset

        {{-- Header form --}}
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Perusahaan</label>
                <select name="company_id" class="form-select" data-journal-company required
                    @disabled($journal->exists || !auth()->user()->hasRole('super_admin'))>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            @selected(old('company_id',$selectedCompanyId)==$company->id)>
                            {{ $company->code ? $company->code.' - ' : '' }}{{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @if($journal->exists || !auth()->user()->hasRole('super_admin'))
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Transaksi</label>
                <input type="date" name="transaction_date" class="form-control"
                    value="{{ old('transaction_date', optional($journal->transaction_date)->format('Y-m-d') ?: date('Y-m-d')) }}"
                    required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cabang</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Pilih Cabang</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected(old('branch_id',$journal->branch_id)==$branch->id)>
                            {{ $branch->code }} - {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">No. Voucher</label>
                <input name="reference_number" class="form-control text-uppercase"
                    value="{{ old('reference_number',$journal->reference_number) }}"
                    placeholder="Otomatis jika kosong">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(['draft'=>'Draft','submitted'=>'Submitted'] as $key=>$label)
                        <option value="{{ $key }}" @selected(old('status',$journal->status)===$key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Bukti Transaksi</label>
                <input type="file" name="attachment" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.webp">
                @if($journal->attachment_path)
                    <div class="form-text">
                        <a href="{{ route('journals.attachment',$journal) }}">Lihat bukti saat ini</a>
                    </div>
                @endif
            </div>
            {{-- Deskripsi jurnal global (TETAP di header, bukan di baris detail) --}}
            <div class="col-md-12">
                <label class="form-label">Deskripsi / Keterangan Transaksi</label>
                <textarea name="description" class="form-control" rows="2" required
                    placeholder="Ringkasan transaksi — contoh: Pembayaran gaji Mei 2026 cabang Jakarta">{{ old('description',$journal->description) }}</textarea>
            </div>
        </div>

        <div class="alert alert-light border small">
            <span data-icon="info" class="app-icon me-1" aria-hidden="true"></span>
            No. Voucher kosong = dibuat otomatis. Kolom <strong>Deskripsi baris</strong> opsional
            — isi hanya jika ada keterangan tambahan per baris selain keterangan utama di atas.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">
                Gunakan <kbd>Enter</kbd> untuk berpindah antar field. Di kolom Debit/Kredit,
                <kbd>Ctrl+D</kbd> mengisi selisih otomatis.
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggle-fiscal-columns">
                Tampilkan Kolom Fiskal
            </button>
        </div>

        <div class="table-responsive">
            {{--
                Urutan kolom BARU (optimal untuk keyboard):
                Akun | Debit | Kredit | Deskripsi Baris | [Fiskal] | [Catatan Fiskal] | Hapus
                Alasan: setelah pilih akun, langsung isi debit/kredit tanpa angkat tangan ke mouse.
                Deskripsi baris diisi terakhir (opsional).
            --}}
            <table class="table" id="details">
                <thead>
                    <tr>
                        <th style="min-width:220px">Akun</th>
                        <th style="min-width:150px">Debit</th>
                        <th style="min-width:150px">Kredit</th>
                        <th style="min-width:180px">
                            Deskripsi Baris
                            <span class="text-muted fw-normal" style="font-size:.75em">(opsional)</span>
                        </th>
                        <th class="fiscal-column d-none" style="min-width:150px">Nilai Fiskal</th>
                        <th class="fiscal-column d-none" style="min-width:160px">Catatan Fiskal</th>
                        <th style="width:42px"></th>
                    </tr>
                </thead>
                <tbody>
                @php
                    $lines = old('details', $journal->details->count()
                        ? $journal->details->toArray()
                        : [[],[]]);
                @endphp
                @foreach($lines as $i => $line)
                    <tr>
                        {{-- Akun --}}
                        <td>
                            <select name="details[{{ $i }}][account_id]"
                                class="form-select journal-account" data-searchable required>
                                <option value="">Pilih Akun</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}"
                                        data-type="{{ $account->type }}"
                                        data-fiscal-deductibility="{{ $account->fiscal_deductibility ?? 100 }}"
                                        data-non-deductible="{{ $account->is_non_deductible ? '1' : '0' }}"
                                        @selected(($line['account_id'] ?? null)==$account->id)>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        {{-- Debit (urutan baru: sebelum deskripsi) --}}
                        <td>
                            <div class="input-group">
                                <input type="text" inputmode="decimal"
                                    name="details[{{ $i }}][debit]"
                                    class="form-control money journal-debit"
                                    data-money-input
                                    value="{{ $line['debit'] ?? 0 }}">
                                <button type="button"
                                    class="btn btn-outline-secondary balance-line"
                                    data-target-side="debit"
                                    title="Isi selisih ke debit (Ctrl+D)"
                                    aria-label="Isi selisih ke debit">=</button>
                            </div>
                        </td>
                        {{-- Kredit (urutan baru: sebelum deskripsi) --}}
                        <td>
                            <div class="input-group">
                                <input type="text" inputmode="decimal"
                                    name="details[{{ $i }}][credit]"
                                    class="form-control money journal-credit"
                                    data-money-input
                                    value="{{ $line['credit'] ?? 0 }}">
                                <button type="button"
                                    class="btn btn-outline-secondary balance-line"
                                    data-target-side="credit"
                                    title="Isi selisih ke kredit (Ctrl+D)"
                                    aria-label="Isi selisih ke kredit">=</button>
                            </div>
                        </td>
                        {{-- Deskripsi baris (paling ujung, opsional) --}}
                        <td>
                            <input name="details[{{ $i }}][description]"
                                class="form-control journal-row-description"
                                value="{{ $line['description'] ?? '' }}"
                                placeholder="Opsional…">
                        </td>
                        {{-- Fiskal (tersembunyi by default) --}}
                        <td class="fiscal-column d-none">
                            <div class="input-group">
                                <input type="text" inputmode="decimal"
                                    name="details[{{ $i }}][fiscal_amount]"
                                    class="form-control money fiscal-amount"
                                    data-money-input
                                    data-fiscal-auto="true"
                                    value="{{ $line['fiscal_amount'] ?? '' }}"
                                    readonly>
                                <button type="button"
                                    class="btn btn-outline-secondary fiscal-override"
                                    title="Edit nilai fiskal"
                                    aria-label="Edit nilai fiskal">Edit</button>
                            </div>
                        </td>
                        <td class="fiscal-column d-none">
                            <input name="details[{{ $i }}][fiscal_note]"
                                class="form-control fiscal-note"
                                value="{{ $line['fiscal_note'] ?? '' }}"
                                maxlength="255"
                                placeholder="Alasan koreksi…">
                        </td>
                        {{-- Hapus baris --}}
                        <td>
                            <button type="button"
                                class="btn btn-outline-danger btn-sm remove-line"
                                title="Hapus baris (Ctrl+Delete)"
                                aria-label="Hapus baris jurnal">
                                <span data-icon="trash" class="app-icon" aria-hidden="true"></span>
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="journal-summary mt-3">
            <div><span>Total Debit</span><strong id="total-debit">0,00</strong></div>
            <div><span>Total Kredit</span><strong id="total-credit">0,00</strong></div>
            <div><span>Selisih</span><strong id="total-difference">0,00</strong></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="add-line">
                <span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Baris
                <kbd class="ms-1" style="font-size:.7em">Ctrl+Enter</kbd>
            </button>
            <div>
                <a href="{{ route('journals.index') }}" class="btn btn-light" data-safe-nav>Batal</a>
                <button class="btn btn-primary">
                    <span data-icon="save" class="app-icon me-1" aria-hidden="true"></span>Simpan Jurnal
                </button>
            </div>
        </div>
    </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* ============================================================
   JOURNAL FORM — keyboard-optimized navigation
   Urutan tab: Akun → Debit → Kredit → Deskripsi → (baris baru)
   ============================================================ */

let index = document.querySelectorAll('#details tbody tr').length;
let forceFiscalColumns = false;

// ── Money helpers ──────────────────────────────────────────────
const fmt   = v  => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
const parse = el => window.parseMoneyInput ? window.parseMoneyInput(el.value) : (parseFloat(el.value.replace(/\./g,'').replace(',','.')) || 0);
const setMoney = (el, v) => { el.value = v ? fmt(v) : ''; el.dispatchEvent(new Event('input', { bubbles: true })); };

// ── Recalculate totals ─────────────────────────────────────────
const recalc = () => {
    const sum = sel => [...document.querySelectorAll(sel)].reduce((t, el) => t + parse(el), 0);
    const debit  = sum('.journal-debit');
    const credit = sum('.journal-credit');
    const diff   = debit - credit;
    document.getElementById('total-debit').textContent  = fmt(debit);
    document.getElementById('total-credit').textContent = fmt(credit);
    const diffEl = document.getElementById('total-difference');
    diffEl.textContent = fmt(diff);
    diffEl.classList.toggle('text-danger',  Math.abs(diff) > 0.009);
    diffEl.classList.toggle('text-success', Math.abs(diff) <= 0.009);
};

// ── Clear opposite side when one is filled ────────────────────
const clearOpposite = el => {
    const row = el.closest('tr');
    if (!row || parse(el) <= 0) return;
    const opp = el.matches('.journal-debit')
        ? row.querySelector('.journal-credit')
        : row.querySelector('.journal-debit');
    if (opp && parse(opp) > 0) { opp.value = '0'; opp.dispatchEvent(new Event('input',{bubbles:true})); }
};

// ── Balance-line (isi selisih) ─────────────────────────────────
const fillBalance = (row, side) => {
    const debitEl  = row.querySelector('.journal-debit');
    const creditEl = row.querySelector('.journal-credit');
    const totD = [...document.querySelectorAll('.journal-debit')].reduce((t,el) => t+(el===debitEl?0:parse(el)),0);
    const totC = [...document.querySelectorAll('.journal-credit')].reduce((t,el) => t+(el===creditEl?0:parse(el)),0);
    const amt = side === 'debit' ? totC - totD : totD - totC;
    if (amt <= 0) return;
    setMoney(side === 'debit' ? debitEl : creditEl, amt);
    setMoney(side === 'debit' ? creditEl : debitEl, 0);
    syncFiscalRow(row);
};

// ── Fiscal helpers ─────────────────────────────────────────────
const selOpt      = row => row.querySelector('.journal-account')?.selectedOptions[0] || null;
const rowNeedFisc = row => {
    const o = selOpt(row); if (!o?.value) return false;
    return Number(o.dataset.fiscalDeductibility||100) < 100 || o.dataset.nonDeductible==='1';
};
const fiscalAmt   = row => {
    const o = selOpt(row); if (!o?.value) return '';
    if (o.dataset.nonDeductible==='1') return 0;
    const amt = Math.max(parse(row.querySelector('.journal-debit')), parse(row.querySelector('.journal-credit')));
    return amt * (Number(o.dataset.fiscalDeductibility||100)/100);
};
const syncFiscalRow = row => {
    const inp = row.querySelector('.fiscal-amount'); if (!inp) return;
    if (inp.dataset.fiscalAuto==='true') { const v=fiscalAmt(row); inp.value = v===''?'':fmt(v); }
    syncFiscalCols();
};
const syncFiscalCols = () => {
    const any = forceFiscalColumns || [...document.querySelectorAll('#details tbody tr')].some(rowNeedFisc);
    document.querySelectorAll('.fiscal-column').forEach(el=>el.classList.toggle('d-none',!any));
    document.getElementById('toggle-fiscal-columns').textContent = (any&&forceFiscalColumns)?'Sembunyikan Kolom Fiskal':'Tampilkan Kolom Fiskal';
};

// ── Ordered navigable fields dalam satu baris ──────────────────
// Urutan: akun → debit → kredit → deskripsi → [fiskal] → [catatan fiskal]
const rowFields = row => [...row.querySelectorAll(
    '.journal-account, .journal-debit, .journal-credit, .journal-row-description, .fiscal-amount:not([readonly]), .fiscal-note'
)].filter(el => !el.closest('.d-none') && !el.disabled);

// ── Tambah baris baru ──────────────────────────────────────────
const addLine = (focusFirst = true) => {
    const tpl = document.querySelector('#details tbody tr').cloneNode(true);
    tpl.querySelectorAll('.searchable-select').forEach(el => el.remove());
    tpl.querySelectorAll('input,select').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${index}]`);
        if (el.tagName === 'INPUT') {
            el.value = el.matches('.journal-debit,.journal-credit') ? '0' : '';
            el.removeAttribute('data-money-bound');
        } else { el.value = ''; }
        if (el.matches('.fiscal-amount')) { el.readOnly = true; el.dataset.fiscalAuto = 'true'; }
    });
    tpl.querySelectorAll('select[data-searchable]').forEach(s => {
        s.dataset.searchableBound = 'false';
        s.classList.remove('visually-hidden');
        s.tabIndex = 0;
    });
    document.querySelector('#details tbody').appendChild(tpl);
    index++;
    window.bootSearchableSelects?.(tpl);
    window.bindMoneyInputs?.(tpl);
    window.renderIcons?.(tpl);
    recalc(); syncFiscalRow(tpl);
    if (focusFirst) rowFields(tpl)[0]?.focus();
};

// ── Keyboard navigation ────────────────────────────────────────
document.getElementById('details').addEventListener('keydown', e => {
    const el   = e.target;
    const row  = el.closest('tr');

    // Ctrl+Enter — tambah baris baru dari mana saja
    if (e.key === 'Enter' && e.ctrlKey && !e.shiftKey) {
        e.preventDefault(); addLine(true); return;
    }

    // Ctrl+Delete — hapus baris aktif
    if ((e.key === 'Delete' || e.key === 'Backspace') && e.ctrlKey && row) {
        const rows = document.querySelectorAll('#details tbody tr');
        if (rows.length > 2) {
            e.preventDefault();
            const nextRow = row.nextElementSibling || row.previousElementSibling;
            row.remove(); recalc(); syncFiscalCols();
            nextRow?.querySelector('.journal-account')?.focus();
        }
        return;
    }

    // Ctrl+D — isi selisih (balance) untuk baris aktif
    if (e.key === 'd' && e.ctrlKey && !e.shiftKey && row) {
        e.preventDefault();
        const side = el.matches('.journal-credit') ? 'credit' : 'debit';
        fillBalance(row, side); return;
    }

    // Enter — navigasi ke field berikutnya dalam urutan logis
    if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.altKey) return;
    if (!el.matches('select,input')) return;

    // Jangan intercept Enter di dalam searchable select (dropdown open)
    if (el.matches('.journal-account') && el.closest('.searchable-select-open')) return;

    e.preventDefault();

    if (!row) return;

    const fields = rowFields(row);
    const idx    = fields.indexOf(el);

    // Masih ada field berikutnya di baris ini
    if (idx !== -1 && idx < fields.length - 1) {
        fields[idx + 1].focus();

        // Select all teks di input angka agar mudah overwrite
        if (fields[idx + 1].matches('input[type="text"]')) {
            fields[idx + 1].select();
        }
        return;
    }

    // Sudah di field terakhir baris — pindah ke baris berikutnya
    const nextRow = row.nextElementSibling;
    if (nextRow) {
        rowFields(nextRow)[0]?.focus();
    } else {
        // Baris terakhir → tambah baris baru
        addLine(true);
    }
});

// ── Input events ───────────────────────────────────────────────
document.addEventListener('input', e => {
    if (e.target.matches('.fiscal-amount')) { e.target.dataset.fiscalAuto = 'false'; return; }
    if (!e.target.matches('.journal-debit,.journal-credit')) return;
    clearOpposite(e.target); recalc(); syncFiscalRow(e.target.closest('tr'));
});

// ── Click events ───────────────────────────────────────────────
document.addEventListener('click', e => {
    // Hapus baris
    const del = e.target.closest('.remove-line');
    if (del && document.querySelectorAll('#details tbody tr').length > 2) {
        del.closest('tr').remove(); recalc(); syncFiscalCols();
    }
    // Balance button
    const bal = e.target.closest('.balance-line');
    if (bal) fillBalance(bal.closest('tr'), bal.dataset.targetSide);
    // Override fiskal
    const ov = e.target.closest('.fiscal-override');
    if (ov) {
        const inp = ov.closest('.input-group').querySelector('.fiscal-amount');
        inp.readOnly = false; inp.dataset.fiscalAuto = 'false'; inp.select(); inp.focus();
    }
});

// ── Account change ─────────────────────────────────────────────
document.getElementById('details').addEventListener('change', e => {
    if (!e.target.matches('.journal-account')) return;
    const row = e.target.closest('tr');
    const fi  = row.querySelector('.fiscal-amount');
    if (fi) { fi.readOnly = true; fi.dataset.fiscalAuto = 'true'; }
    syncFiscalRow(row);

    // Setelah pilih akun via keyboard, fokus otomatis ke Debit
    // (berlaku jika akun dipilih dengan Enter di searchable select)
    setTimeout(() => {
        if (document.activeElement === e.target || document.activeElement?.closest('.searchable-select')) {
            row.querySelector('.journal-debit')?.focus();
            row.querySelector('.journal-debit')?.select();
        }
    }, 80);
});

// ── Toggle fiskal ──────────────────────────────────────────────
document.getElementById('toggle-fiscal-columns').addEventListener('click', () => {
    forceFiscalColumns = !forceFiscalColumns; syncFiscalCols();
});

// ── Add line button ────────────────────────────────────────────
document.getElementById('add-line').addEventListener('click', () => addLine(true));

// ── Voucher & company ──────────────────────────────────────────
const voucherInput  = document.querySelector('[name="reference_number"]');
const companyInput  = document.querySelector('[data-journal-company]');
voucherInput?.addEventListener('input', () => { voucherInput.value = voucherInput.value.toUpperCase(); });
companyInput?.addEventListener('change', () => {
    const url = new URL(window.location.href);
    url.searchParams.set('company_id', companyInput.value);
    window.location.href = url.toString();
});

// ── Init ───────────────────────────────────────────────────────
recalc();
document.querySelectorAll('#details tbody tr').forEach(syncFiscalRow);

// Save shortcut, unsaved-change guard, and local draft recovery.
const journalForm = document.getElementById('journal-form');
const autosaveKey = journalForm?.dataset.autosaveKey;
let journalFormSubmitting = false;
let journalFormDirty = false;

const journalSnapshot = () => {
    const data = {};
    new FormData(journalForm).forEach((value, key) => {
        if (key === '_token' || key === '_method' || value instanceof File) return;
        data[key] = value;
    });

    return data;
};

const restoreJournalSnapshot = data => {
    const detailIndexes = Object.keys(data)
        .map(key => key.match(/^details\[(\d+)]/))
        .filter(Boolean)
        .map(match => Number(match[1]));
    const targetRows = detailIndexes.length ? Math.max(...detailIndexes) + 1 : 0;
    while (document.querySelectorAll('#details tbody tr').length < targetRows) {
        addLine(false);
    }

    Object.entries(data).forEach(([key, value]) => {
        const field = journalForm.elements.namedItem(key);
        if (!field || field.type === 'file') return;
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });
    recalc();
    document.querySelectorAll('#details tbody tr').forEach(syncFiscalRow);
};

if (journalForm && autosaveKey) {
    const savedDraft = localStorage.getItem(autosaveKey);
    if (savedDraft && confirm('Ada draft lokal yang belum tersimpan. Pulihkan draft ini?')) {
        try {
            restoreJournalSnapshot(JSON.parse(savedDraft));
        } catch (error) {
            localStorage.removeItem(autosaveKey);
        }
    }

    journalForm.addEventListener('input', () => {
        journalFormDirty = true;
        localStorage.setItem(autosaveKey, JSON.stringify(journalSnapshot()));
    });
    journalForm.addEventListener('change', () => {
        journalFormDirty = true;
        localStorage.setItem(autosaveKey, JSON.stringify(journalSnapshot()));
    });
    journalForm.addEventListener('submit', () => {
        journalFormSubmitting = true;
        journalFormDirty = false;
        localStorage.removeItem(autosaveKey);
    });
}

document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        journalForm?.requestSubmit();
    }
});

window.addEventListener('beforeunload', event => {
    if (!journalFormDirty || journalFormSubmitting) return;
    event.preventDefault();
    event.returnValue = '';
});

document.querySelectorAll('[data-safe-nav]').forEach(link => {
    link.addEventListener('click', event => {
        if (!journalFormDirty) return;
        if (!confirm('Ada perubahan yang belum disimpan. Tinggalkan halaman ini?')) {
            event.preventDefault();
        }
    });
});
</script>
@endpush
