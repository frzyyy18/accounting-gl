@extends('layouts.app', ['title' => 'Dashboard Pajak'])

@section('content')
@php
    $monthLabels = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $taxFrom = \Illuminate\Support\Carbon::create($selectedYear, $selectedMonth)->startOfMonth()->toDateString();
    $taxTo = \Illuminate\Support\Carbon::create($selectedYear, $selectedMonth)->endOfMonth()->toDateString();
    $selectedCompanyParams = $selectedCompanyId ? ['company_id' => $selectedCompanyId] : [];
    $selectedCompanyName = $selectedCompanyId
        ? optional($companies->firstWhere('id', $selectedCompanyId))->name
        : 'Semua Perusahaan';
@endphp

<div class="card p-3 mb-4 no-print">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="fw-bold">Filter Dashboard Pajak</div>
            <div class="text-muted small">Menampilkan data: {{ $selectedCompanyName }} | {{ $monthLabels[$selectedMonth] ?? $selectedMonthName }} {{ $selectedYear }}</div>
        </div>
        <form method="get" action="{{ route('dashboards.tax') }}" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Bulan</label>
                <select name="month" class="form-select">
                    @foreach($monthLabels as $monthValue => $monthLabel)
                        <option value="{{ $monthValue }}" @selected((int) $selectedMonth === $monthValue)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Tahun</label>
                <select name="year" class="form-select">
                    @foreach(range(now()->year - 2, now()->year + 1) as $yearValue)
                        <option value="{{ $yearValue }}" @selected((int) $selectedYear === $yearValue)>{{ $yearValue }}</option>
                    @endforeach
                </select>
            </div>
            @if($companies->count() > 1)
                <div class="col-sm-auto">
                    <label class="form-label small mb-1">Perusahaan</label>
                    <select name="company_id" class="form-select" data-searchable>
                        @if($canSelectAllCompanies)
                            <option value="" @selected(blank($selectedCompanyId))>Semua Perusahaan</option>
                        @endif
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) $selectedCompanyId === (int) $company->id)>{{ $company->code ? $company->code.' - ' : '' }}{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                @if($selectedCompanyId)
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
            @endif
            @if($journalStatus)
                <input type="hidden" name="journal_status" value="{{ $journalStatus }}">
            @endif
            <div class="col-sm-auto">
                <button class="btn btn-outline-secondary"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Terapkan</button>
            </div>
        </form>
    </div>
</div>

@if($pendingApprovalCount > 0 || $draftOldCount > 0 || $rejectedCount > 0 || $npwpMissing > 0 || $uncategorizedTaxAccounts->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-4 no-print">
        @if($pendingApprovalCount > 0)
            <a href="{{ route('journals.index', array_merge($selectedCompanyParams, ['status' => 'submitted', 'from' => $period['start'], 'to' => $period['end']])) }}" class="badge text-bg-danger fs-6 fw-normal py-2 px-3">
                <span data-icon="pending" class="app-icon me-1" aria-hidden="true"></span>
                {{ number_format($pendingApprovalCount, 0, ',', '.') }} jurnal menunggu approval
            </a>
        @endif

        @if($draftOldCount > 0)
            <a href="{{ route('journals.index', array_merge($selectedCompanyParams, ['status' => 'draft'])) }}" class="badge text-bg-warning fs-6 fw-normal py-2 px-3 text-dark">
                <span data-icon="danger" class="app-icon me-1" aria-hidden="true"></span>
                {{ number_format($draftOldCount, 0, ',', '.') }} draft lebih dari 3 hari
            </a>
        @endif

        @if($rejectedCount > 0)
            <a href="{{ route('journals.index', array_merge($selectedCompanyParams, ['status' => 'rejected'])) }}" class="badge text-bg-danger fs-6 fw-normal py-2 px-3" style="opacity:.8">
                <span data-icon="danger" class="app-icon me-1" aria-hidden="true"></span>
                {{ number_format($rejectedCount, 0, ',', '.') }} jurnal ditolak belum direvisi
            </a>
        @endif

        @if($npwpMissing > 0)
            <a href="{{ route('companies.index') }}" class="badge text-bg-warning fs-6 fw-normal py-2 px-3 text-dark">
                <span data-icon="shield" class="app-icon me-1" aria-hidden="true"></span>
                {{ number_format($npwpMissing, 0, ',', '.') }} perusahaan NPWP belum lengkap
            </a>
        @endif

        @if($uncategorizedTaxAccounts->isNotEmpty())
            <a href="{{ route('accounts.index', ['search' => 'pajak']) }}" class="badge text-bg-warning fs-6 fw-normal py-2 px-3 text-dark">
                <span data-icon="danger" class="app-icon me-1" aria-hidden="true"></span>
                {{ number_format($uncategorizedTaxAccounts->count(), 0, ',', '.') }} akun pajak belum dikategorikan
            </a>
        @endif
    </div>
@endif

@php
    $taxCards = [
        ['label' => 'PPN', 'value' => $ppnPayable, 'prev' => $ppnPrev, 'icon' => 'receipt', 'tone' => 'primary'],
        ['label' => 'PPh 21', 'value' => $pph21Payable, 'prev' => $pph21Prev, 'icon' => 'receipt', 'tone' => 'primary'],
        ['label' => 'PPh 23', 'value' => $pph23Payable, 'prev' => $pph23Prev, 'icon' => 'receipt', 'tone' => 'primary'],
        ['label' => 'PPh Final', 'value' => $pphFinalPayable, 'prev' => $pphFinalPrev, 'icon' => 'calculator', 'tone' => 'primary'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($taxCards as $card)
        @php
            $diff = $card['prev'] > 0 ? (($card['value'] - $card['prev']) / $card['prev']) * 100 : null;
            $up = $diff !== null && $diff > 0;
            $down = $diff !== null && $diff < 0;
        @endphp
        <div class="col-md-6 col-xl-3">
            <div class="card metric-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="metric-label">{{ $card['label'] }} Periode Ini</div>
                    <div class="metric-icon metric-icon-{{ $card['tone'] }}">
                        <span data-icon="{{ $card['icon'] }}" class="app-icon" aria-hidden="true"></span>
                    </div>
                </div>
                <div class="metric-value">{{ rupiah($card['value']) }}</div>
                <div class="metric-meta d-flex align-items-center gap-1 flex-wrap">
                    @if($diff !== null)
                        <span class="{{ $up ? 'text-danger' : ($down ? 'text-success' : 'text-muted') }}">
                            <span data-icon="{{ $up ? 'arrowUp' : ($down ? 'arrowDown' : 'arrowDownUp') }}" class="app-icon" aria-hidden="true"></span>
                            {{ number_format(abs($diff), 1, ',', '.') }}%
                        </span>
                        <span class="text-muted">vs {{ rupiah($card['prev']) }}</span>
                    @else
                        <span class="text-muted">Tidak ada data bulan lalu</span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card p-3 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div class="fw-semibold text-muted small text-uppercase">Ringkasan Fiskal {{ $selectedMonthName }} {{ $selectedYear }}</div>
        <div class="d-flex flex-wrap gap-4">
            <div>
                <div class="small text-muted">Pendapatan</div>
                <div class="fw-bold">{{ rupiah($income) }}</div>
            </div>
            <div class="text-muted align-self-end pb-1">-</div>
            <div>
                <div class="small text-muted">Beban</div>
                <div class="fw-bold">{{ rupiah($expense) }}</div>
            </div>
            <div class="text-muted align-self-end pb-1">=</div>
            <div>
                <div class="small text-muted">Laba Komersial</div>
                <div class="fw-bold {{ $profitBeforeTax < 0 ? 'text-danger' : 'text-success' }}">{{ rupiah($profitBeforeTax) }}</div>
            </div>
            <div class="border-start ps-4">
                <div class="small text-muted">Est. PPh Badan ({{ number_format(corporateTaxRate() * 100, 0) }}%)</div>
                <div class="fw-bold text-primary">{{ rupiah($estimatedCorporateTax) }}</div>
            </div>
        </div>
        @if($selectedCompanyId)
            <a href="{{ route('tax-reports.reconciliation', ['company_id' => $selectedCompanyId, 'from' => $taxFrom, 'to' => $taxTo]) }}" class="btn btn-outline-primary btn-sm text-nowrap">
                <span data-icon="calculator" class="app-icon me-1" aria-hidden="true"></span>
                Rekonsiliasi Fiskal Lengkap
            </a>
        @else
            <button class="btn btn-outline-secondary btn-sm text-nowrap" disabled>
                <span data-icon="calculator" class="app-icon me-1" aria-hidden="true"></span>
                Pilih perusahaan untuk rekonsiliasi
            </button>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card chart-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Tren Pajak 6 Bulan Terakhir</span>
                <span class="text-muted small">PPN, PPh 21, Laba Fiskal</span>
            </div>
            <div class="chart-wrap">
                <canvas id="taxTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card p-3 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="fw-semibold mb-1">Akses Cepat Laporan</div>
                <div class="text-muted small mb-3">Periode: {{ $selectedMonthName }} {{ $selectedYear }}</div>
            </div>
            @php
                $shortcuts = [
                    ['label' => 'Rekap Pajak', 'route' => 'tax-reports.summary', 'icon' => 'receipt'],
                    ['label' => 'Rekonsiliasi Fiskal', 'route' => 'tax-reports.reconciliation', 'icon' => 'calculator'],
                    ['label' => 'Buku Besar', 'route' => 'reports.ledger', 'icon' => 'book'],
                    ['label' => 'Laba Rugi', 'route' => 'reports.profit-loss', 'icon' => 'graphUp'],
                ];
            @endphp
            <div class="d-flex flex-column gap-2">
                @foreach($shortcuts as $shortcut)
                    @if($selectedCompanyId)
                        <a href="{{ route($shortcut['route'], ['from' => $taxFrom, 'to' => $taxTo, 'company_id' => $selectedCompanyId]) }}" class="btn btn-outline-secondary btn-sm text-start">
                    @else
                        <button class="btn btn-outline-secondary btn-sm text-start" disabled>
                    @endif
                        <span data-icon="{{ $shortcut['icon'] }}" class="app-icon me-1" aria-hidden="true"></span>
                        {{ $shortcut['label'] }}
                    @if($selectedCompanyId)
                        </a>
                    @else
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span>Jurnal</span>
        <div class="text-end">
            <a href="{{ route('journals.index', array_merge($selectedCompanyParams, ['status' => 'submitted', 'from' => $period['start'], 'to' => $period['end']])) }}" class="badge {{ $pendingApprovalCount > 0 ? 'text-bg-danger' : 'text-bg-success' }}">
                {{ $pendingApprovalCount > 0 ? number_format($pendingApprovalCount, 0, ',', '.').' Menunggu Approval' : 'Semua Disetujui' }}
            </a>
            @if($pendingApprovalTotal > $pendingApprovalCount)
                <div class="small text-muted mt-1">
                    + {{ number_format($pendingApprovalTotal - $pendingApprovalCount, 0, ',', '.') }} jurnal di periode lain
                </div>
            @endif
        </div>
    </div>

    <div class="p-3 pb-0 no-print">
        <ul class="nav nav-pills gap-2">
            @php
                $tabs = [
                    'action' => 'Perlu Tindak'.($draftOldCount + $rejectedCount > 0 ? ' ('.number_format($draftOldCount + $rejectedCount, 0, ',', '.').')' : ''),
                    'submitted' => 'Submitted'.($pendingApprovalCount > 0 ? ' ('.number_format($pendingApprovalCount, 0, ',', '.').')' : ''),
                    'posted' => 'Posted',
                ];
            @endphp
            @foreach($tabs as $tabKey => $tabLabel)
                @php
                    $active = ($journalStatus ?? 'action') === $tabKey;
                    $params = array_merge(request()->except('journal_status'), ['journal_status' => $tabKey]);
                @endphp
                <li class="nav-item">
                    <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route('dashboards.tax', $params) }}">{{ $tabLabel }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="table-responsive">
        <table class="table table-hover responsive-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Cabang</th>
                    <th>No. Jurnal</th>
                    <th>Keterangan</th>
                    <th class="money">Nominal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentJournals as $journal)
                <tr>
                    <td data-label="Tanggal">{{ $journal->transaction_date->format('d/m/Y') }}</td>
                    <td data-label="Cabang">{{ $journal->branch?->code ?: '-' }}</td>
                    <td data-label="No. Jurnal">
                        <a class="fw-semibold" href="{{ route('journals.show', $journal) }}">
                            {{ $journal->reference_number ?: $journal->journal_number }}
                        </a>
                    </td>
                    <td data-label="Keterangan" class="text-truncate" style="max-width:200px">{{ $journal->description }}</td>
                    <td data-label="Nominal" class="money">{{ rupiah($journal->total_amount) }}</td>
                    <td data-label="Status">@include('journals.partials.status-badge', ['status' => $journal->status])</td>
                </tr>
            @empty
                <tr><td colspan="6">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const taxTrend = @json($taxTrend);
const rupiahFmt = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
const taxTrendCanvas = document.getElementById('taxTrendChart');

if (taxTrendCanvas) {
    new Chart(taxTrendCanvas, {
        type: 'bar',
        data: {
            labels: taxTrend.map(item => item.label),
            datasets: [
                {
                    type: 'bar',
                    label: 'PPN',
                    data: taxTrend.map(item => item.ppn),
                    backgroundColor: 'rgba(37,99,235,.75)',
                    borderRadius: 4,
                },
                {
                    type: 'bar',
                    label: 'PPh 21',
                    data: taxTrend.map(item => item.pph21),
                    backgroundColor: 'rgba(15,118,110,.75)',
                    borderRadius: 4,
                },
                {
                    type: 'line',
                    label: 'Laba Fiskal',
                    data: taxTrend.map(item => item.profit),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.1)',
                    tension: .35,
                    fill: false,
                    pointRadius: 3,
                    yAxisID: 'y2',
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index' },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: context => `${context.dataset.label}: ${rupiahFmt.format(context.parsed.y)}`,
                    },
                },
            },
            scales: {
                y: { ticks: { callback: value => rupiahFmt.format(value) }, grid: { color: '#edf2f7' } },
                y2: { position: 'right', ticks: { callback: value => rupiahFmt.format(value) }, grid: { display: false } },
                x: { grid: { display: false } },
            },
        },
    });
}
</script>
@endpush
