@extends('layouts.app', ['title' => 'Rekonsiliasi Fiskal'])

@section('content')
<div class="card p-3 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
        <div>
            <h5 class="mb-1">Rekonsiliasi Fiskal</h5>
            <div class="text-muted small">{{ $company?->name }} | Dasar: jurnal posted</div>
        </div>
        <div class="text-lg-end small text-muted">
            <div>{{ request('from') ?: 'Awal data' }} - {{ request('to') ?: 'Akhir data' }}</div>
            <div>Estimasi PPh Badan memakai tarif {{ number_format(corporateTaxRate() * 100, 0) }}%</div>
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
        @else
            <input type="hidden" name="company_id" value="{{ $company?->id }}">
        @endif
        <div class="col-md-3">
            <label class="form-label small mb-1">Cabang</label>
            <select name="branch_id" class="form-select" data-searchable>
                <option value="">Semua Cabang</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small mb-1">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Urutan</label><select name="sort_direction" class="form-select"><option value="newest" @selected(($sortDirection ?? request('sort_direction', 'newest')) === 'newest')>Terbaru dulu</option><option value="oldest" @selected(($sortDirection ?? request('sort_direction')) === 'oldest')>Terlama dulu</option></select></div>
        <div class="col-md-3 d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Filter</button>
            <button name="export" value="excel" class="btn btn-outline-success"><span data-icon="fileExcel" class="app-icon me-1" aria-hidden="true"></span>Excel</button>
            <button name="export" value="pdf" class="btn btn-outline-danger"><span data-icon="filePdf" class="app-icon me-1" aria-hidden="true"></span>PDF</button>
        </div>
    </div>
</form>

<div class="card mb-3">
    <div class="card-header">Ringkasan</div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm responsive-table">
            <thead><tr><th class="money">Laba Komersial</th><th class="money">Koreksi Positif</th><th class="money">Koreksi Negatif</th><th class="money">Laba Fiskal</th><th class="money">Estimasi PPh Badan {{ number_format(corporateTaxRate() * 100, 0) }}%</th></tr></thead>
            <tbody>
                <tr>
                    <td data-label="Laba Komersial" class="money">{{ rupiah($commercialProfit) }}</td>
                    <td data-label="Koreksi Positif" class="money">{{ rupiah($positiveCorrectionTotal) }}</td>
                    <td data-label="Koreksi Negatif" class="money">{{ rupiah($negativeCorrectionTotal) }}</td>
                    <td data-label="Laba Fiskal" class="money">{{ rupiah($fiscalProfit) }}</td>
                    <td data-label="Estimasi PPh Badan {{ number_format(corporateTaxRate() * 100, 0) }}%" class="money">{{ rupiah($estimatedCorporateTax) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Rincian Koreksi Positif</div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm responsive-table">
            <thead><tr><th>Tanggal</th><th>No. Jurnal</th><th>Akun</th><th>Keterangan</th><th class="money">Komersial</th><th class="money">Fiskal</th><th class="money">Koreksi</th><th>Catatan Fiskal</th></tr></thead>
            <tbody>
            @forelse($positiveCorrections as $row)
                <tr>
                    <td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d/m/Y') }}</td>
                    <td data-label="No. Jurnal">{{ $row->journal_number }}</td>
                    <td data-label="Akun">{{ $row->code }} - {{ $row->name }}</td>
                    <td data-label="Keterangan">{{ $row->description ?: '-' }}</td>
                    <td data-label="Komersial" class="money">{{ rupiah($row->commercial_amount) }}</td>
                    <td data-label="Fiskal" class="money">{{ rupiah($row->fiscal_amount) }}</td>
                    <td data-label="Koreksi" class="money">{{ rupiah($row->correction_amount) }}</td>
                    <td data-label="Catatan Fiskal">{{ $row->fiscal_note ?: ($row->is_non_deductible ? 'Non-deductible' : ($row->fiscal_deductibility < 100 ? $row->fiscal_deductibility.'% deductible' : '-')) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Rincian Koreksi Negatif</div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm responsive-table">
            <thead><tr><th>Tanggal</th><th>No. Jurnal</th><th>Akun</th><th>Keterangan</th><th class="money">Komersial</th><th class="money">Fiskal</th><th class="money">Koreksi</th><th>Catatan Fiskal</th></tr></thead>
            <tbody>
            @forelse($negativeCorrections as $row)
                <tr>
                    <td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d/m/Y') }}</td>
                    <td data-label="No. Jurnal">{{ $row->journal_number }}</td>
                    <td data-label="Akun">{{ $row->code }} - {{ $row->name }}</td>
                    <td data-label="Keterangan">{{ $row->description ?: '-' }}</td>
                    <td data-label="Komersial" class="money">{{ rupiah($row->commercial_amount) }}</td>
                    <td data-label="Fiskal" class="money">{{ rupiah($row->fiscal_amount) }}</td>
                    <td data-label="Koreksi" class="money">{{ rupiah(abs($row->correction_amount)) }}</td>
                    <td data-label="Catatan Fiskal">{{ $row->fiscal_note ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
