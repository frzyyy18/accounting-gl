@extends('layouts.app', ['title' => 'Laporan Pajak'])

@section('content')
<div class="card p-3 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
        <div>
            <h5 class="mb-1">Rekap Pajak Perusahaan</h5>
            <div class="text-muted small">{{ $company?->name }} | NPWP: {{ $company?->tax_number ?: '-' }}</div>
        </div>
        <div class="text-lg-end small text-muted">
            <div>Dasar: jurnal posted</div>
            <div>{{ request('from') ?: 'Awal data' }} - {{ request('to') ?: 'Akhir data' }}</div>
            <div>Akun pajak dihitung dari field Kategori Pajak di master akun.</div>
        </div>
    </div>
</div>

<form class="card p-3 mb-3 no-print">
    <div class="row g-2 align-items-end">
        @if($companies->count() > 1)
            <div class="col-md-3">
                <label class="form-label small mb-1">Perusahaan</label>
                <select name="company_id" class="form-select" data-searchable>
                    @foreach($companies as $companyOption)
                        <option value="{{ $companyOption->id }}" @selected(request('company_id', $company?->id) == $companyOption->id)>{{ $companyOption->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-2"><label class="form-label small mb-1">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Cabang</label>
            <select name="branch_id" class="form-select" data-searchable>
                <option value="">Semua Cabang</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small mb-1">Urutan</label><select name="sort_direction" class="form-select"><option value="newest" @selected(($sortDirection ?? request('sort_direction', 'newest')) === 'newest')>Terbaru dulu</option><option value="oldest" @selected(($sortDirection ?? request('sort_direction')) === 'oldest')>Terlama dulu</option></select></div>
        <div class="col-md-3 d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Filter</button>
            <button name="export" value="excel" class="btn btn-outline-success"><span data-icon="fileExcel" class="app-icon me-1" aria-hidden="true"></span>Excel</button>
            <button name="export" value="pdf" class="btn btn-outline-danger"><span data-icon="filePdf" class="app-icon me-1" aria-hidden="true"></span>PDF</button>
        </div>
    </div>
</form>

@if($uncategorizedTaxAccounts->isNotEmpty())
    <div class="alert alert-warning no-print">
        <div class="fw-semibold mb-1">Ada akun berindikasi pajak yang belum punya kategori pajak.</div>
        <div class="small mb-2">Akun ini sudah memiliki jurnal posted pada periode laporan, tetapi belum dihitung ke PPN/PPh karena `tax_category` masih kosong.</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($uncategorizedTaxAccounts as $taxAccount)
                <a href="{{ route('accounts.edit', $taxAccount->id) }}" class="badge text-bg-warning text-dark">
                    {{ $taxAccount->code }} - {{ $taxAccount->name }}
                </a>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['label' => 'Pendapatan Fiskal Awal', 'value' => $income, 'tone' => 'success'],
        ['label' => 'Beban Fiskal Awal', 'value' => $expense, 'tone' => 'danger'],
        ['label' => 'Laba Sebelum Pajak', 'value' => $profitBeforeTax, 'tone' => $profitBeforeTax >= 0 ? 'success' : 'danger'],
        ['label' => 'Estimasi PPh Badan '.number_format(corporateTaxRate() * 100, 0).'%', 'value' => $estimatedCorporateTax, 'tone' => 'primary'],
        ['label' => 'PPN Bersih', 'value' => $ppnPayable, 'tone' => $ppnPayable >= 0 ? 'primary' : 'success'],
        ['label' => 'PPh 21 Bersih', 'value' => $pph21Payable, 'tone' => $pph21Payable >= 0 ? 'primary' : 'success'],
        ['label' => 'PPh 23 Bersih', 'value' => $pph23Payable, 'tone' => $pph23Payable >= 0 ? 'primary' : 'success'],
        ['label' => 'PPh Final Bersih', 'value' => $pphFinalPayable, 'tone' => $pphFinalPayable >= 0 ? 'primary' : 'success'],
    ] as $item)
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card p-3">
                <div class="metric-label">{{ $item['label'] }}</div>
                <div class="metric-value {{ $item['tone'] === 'danger' ? 'text-danger' : '' }}">{{ rupiah($item['value']) }}</div>
                <div class="metric-meta">Perhitungan awal dari akun GL</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    @foreach([
        'PPN' => $ppnRows,
        'PPh 21' => $pph21Rows,
        'PPh 23' => $pph23Rows,
        'PPh Final' => $pphFinalRows,
    ] as $title => $taxRows)
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Rincian {{ $title }}</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm responsive-table">
                        <thead><tr><th>Kode</th><th>Akun</th><th class="money">Debit</th><th class="money">Kredit</th><th class="money">Saldo Pajak</th></tr></thead>
                        <tbody>
                        @forelse($taxRows as $row)
                            @php
                                $balance = in_array($row->type, ['asset', 'expense', 'other_expense'], true)
                                    ? (float) $row->total_debit - (float) $row->total_credit
                                    : (float) $row->total_credit - (float) $row->total_debit;
                            @endphp
                            <tr>
                                <td data-label="Kode">{{ $row->code }}</td>
                                <td data-label="Akun">{{ $row->name }}</td>
                                <td data-label="Debit" class="money">{{ rupiah($row->total_debit) }}</td>
                                <td data-label="Kredit" class="money">{{ rupiah($row->total_credit) }}</td>
                                <td data-label="Saldo Pajak" class="money">{{ rupiah($balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Belum ada jurnal posted untuk akun kategori {{ $title }} pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">Rincian Fiskal Awal Pendapatan dan Beban</div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm responsive-table">
            <thead><tr><th>Kode</th><th>Akun</th><th>Tipe</th><th class="money">Debit</th><th class="money">Kredit</th><th class="money">Nilai Fiskal Awal</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $value = in_array($row->type, ['revenue', 'other_income'], true)
                        ? (float) $row->total_credit - (float) $row->total_debit
                        : (float) $row->total_debit - (float) $row->total_credit;
                @endphp
                <tr>
                    <td data-label="Kode">{{ $row->code }}</td>
                    <td data-label="Akun">{{ $row->name }}</td>
                    <td data-label="Tipe">{{ \App\Models\Account::TYPES[$row->type] }}</td>
                    <td data-label="Debit" class="money">{{ rupiah($row->total_debit) }}</td>
                    <td data-label="Kredit" class="money">{{ rupiah($row->total_credit) }}</td>
                    <td data-label="Nilai Fiskal Awal" class="money">{{ rupiah($value) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
