@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Pendapatan Bulan Ini', 'value' => $income, 'icon' => 'graphUp', 'format' => 'money', 'tone' => 'success', 'meta' => 'Jurnal posted bulan berjalan'],
        ['label' => 'Beban Bulan Ini', 'value' => $expense, 'icon' => 'graphDown', 'format' => 'money', 'tone' => 'danger', 'meta' => 'Akumulasi beban dan beban lain'],
        ['label' => 'Laba/Rugi Bulan Ini', 'value' => $profit, 'icon' => 'calculator', 'format' => 'money', 'tone' => $profit >= 0 ? 'success' : 'danger', 'meta' => 'Pendapatan dikurangi beban'],
        ['label' => 'Pending Approval', 'value' => $pending, 'icon' => 'pending', 'format' => 'number', 'tone' => 'primary', 'meta' => $postedThisMonth.' jurnal posted bulan ini'],
        ['label' => 'Jurnal Posted', 'value' => $postedThisMonth, 'icon' => 'journalCheck', 'format' => 'number', 'tone' => 'primary', 'meta' => 'Total jurnal posted bulan berjalan'],
        ['label' => 'Akun Aktif', 'value' => $activeAccounts, 'icon' => 'accountList', 'format' => 'number', 'tone' => 'success', 'meta' => 'Chart of Account siap transaksi'],
    ] as $item)
    <div class="col-md-6 col-xl-4">
        <div class="card metric-card p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="metric-label">{{ $item['label'] }}</div>
                <div class="metric-icon metric-icon-{{ $item['tone'] }}"><span data-icon="{{ $item['icon'] }}" class="app-icon" aria-hidden="true"></span></div>
            </div>
            <div>
                <div class="metric-value {{ $item['tone'] === 'danger' ? 'text-danger' : '' }}">
                    {{ $item['format'] === 'money' ? rupiah($item['value']) : number_format($item['value'], 0, ',', '.') }}
                </div>
                <div class="metric-meta">{{ $item['meta'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@php
    $exportFrom = now()->startOfMonth()->format('Y-m-d');
    $exportTo = now()->endOfMonth()->format('Y-m-d');
    $exportShortcuts = [
        ['label' => 'Neraca Saldo', 'route' => 'reports.trial-balance', 'icon' => 'columns'],
        ['label' => 'Laba Rugi', 'route' => 'reports.profit-loss', 'icon' => 'graphUp'],
        ['label' => 'Neraca', 'route' => 'reports.balance-sheet', 'icon' => 'bank'],
        ['label' => 'Arus Kas', 'route' => 'reports.cash-flow', 'icon' => 'cashStack'],
    ];
@endphp
<div class="card export-shortcuts p-3 mb-4 no-print">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="fw-bold">Export Cepat Laporan</div>
            <div class="text-muted small">{{ now()->startOfMonth()->format('d/m/Y') }} - {{ now()->endOfMonth()->format('d/m/Y') }}</div>
        </div>
        <div class="export-shortcut-list">
            @foreach($exportShortcuts as $shortcut)
                <div class="export-shortcut-item">
                    <span><span data-icon="{{ $shortcut['icon'] }}" class="app-icon me-1" aria-hidden="true"></span>{{ $shortcut['label'] }}</span>
                    <a class="btn btn-outline-success btn-sm" href="{{ route($shortcut['route'], ['from' => $exportFrom, 'to' => $exportTo, 'status' => 'posted', 'export' => 'excel']) }}" aria-label="Export Excel {{ $shortcut['label'] }}"><span data-icon="fileExcel" class="app-icon" aria-hidden="true"></span></a>
                    <a class="btn btn-outline-danger btn-sm" href="{{ route($shortcut['route'], ['from' => $exportFrom, 'to' => $exportTo, 'status' => 'posted', 'export' => 'pdf']) }}" aria-label="Export PDF atau print {{ $shortcut['label'] }}"><span data-icon="printer" class="app-icon" aria-hidden="true"></span></a>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card chart-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Tren Pendapatan, Beban, dan Laba/Rugi</span>
                <span class="text-muted small">6 bulan terakhir</span>
            </div>
            <div class="chart-wrap">
                <canvas id="monthlyPerformanceChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card chart-card">
            <div class="card-header">Status Jurnal</div>
            <div class="chart-wrap chart-wrap-sm">
                <canvas id="journalStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card chart-card">
            <div class="card-header">Komposisi Akun</div>
            <div class="chart-wrap chart-wrap-sm">
                <canvas id="accountCompositionChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Ringkasan 6 Bulan</span>
                <span class="text-muted small">Berdasarkan jurnal posted</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover responsive-table">
                    <thead><tr><th>Periode</th><th class="money">Pendapatan</th><th class="money">Beban</th><th class="money">Laba/Rugi</th></tr></thead>
                    <tbody>
                    @foreach($monthlyChart['labels'] as $index => $label)
                        @php $monthlyProfit = $monthlyChart['profit'][$index]; @endphp
                        <tr>
                            <td data-label="Periode">{{ $label }}</td>
                            <td data-label="Pendapatan" class="money">{{ rupiah($monthlyChart['income'][$index]) }}</td>
                            <td data-label="Beban" class="money">{{ rupiah($monthlyChart['expense'][$index]) }}</td>
                            <td data-label="Laba/Rugi" class="money {{ $monthlyProfit < 0 ? 'text-danger' : '' }}">{{ rupiah($monthlyProfit) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Transaksi Terbaru</span>
        @if(auth()->user()->canManage('journal.create'))
            <a class="btn btn-primary btn-sm" href="{{ route('journals.create') }}"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Jurnal</a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover responsive-table">
            <thead><tr><th>Tanggal</th>@if(auth()->user()->hasRole('super_admin'))<th>Perusahaan</th>@endif<th>No. Jurnal</th><th>Keterangan</th><th>Status</th><th>Dibuat Oleh</th></tr></thead>
            <tbody>
            @forelse($recentJournals as $journal)
                <tr>
                    <td data-label="Tanggal">{{ $journal->transaction_date->format('d/m/Y') }}</td>
                    @if(auth()->user()->hasRole('super_admin'))<td data-label="Perusahaan">{{ $journal->company?->name }}</td>@endif
                    <td data-label="No. Jurnal"><a class="fw-semibold" href="{{ route('journals.show',$journal) }}">{{ $journal->journal_number }}</a></td>
                    <td data-label="Keterangan">{{ $journal->description }}</td>
                    <td data-label="Status">@include('journals.partials.status-badge', ['status' => $journal->status])</td>
                    <td data-label="Dibuat Oleh">{{ $journal->creator?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('super_admin') ? 6 : 5 }}">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const rupiah = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const monthlyChart = @json($monthlyChart);
const statusChart = @json($statusChart);
const accountChart = @json($accountChart);

new Chart(document.getElementById('monthlyPerformanceChart'), {
    type: 'bar',
    data: {
        labels: monthlyChart.labels,
        datasets: [
            { type: 'bar', label: 'Pendapatan', data: monthlyChart.income, backgroundColor: 'rgba(15, 118, 110, .78)', borderRadius: 5 },
            { type: 'bar', label: 'Beban', data: monthlyChart.expense, backgroundColor: 'rgba(185, 28, 28, .72)', borderRadius: 5 },
            { type: 'line', label: 'Laba/Rugi', data: monthlyChart.profit, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, .12)', tension: .35, fill: false, pointRadius: 3 },
        ],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: context => `${context.dataset.label}: ${rupiah.format(context.parsed.y)}` } },
        },
        scales: {
            y: { ticks: { callback: value => rupiah.format(value) }, grid: { color: '#edf2f7' } },
            x: { grid: { display: false } },
        },
    },
});

new Chart(document.getElementById('journalStatusChart'), {
    type: 'doughnut',
    data: {
        labels: statusChart.labels,
        datasets: [{ data: statusChart.values, backgroundColor: ['#64748b', '#f59e0b', '#2563eb', '#0f766e', '#b91c1c', '#94a3b8'], borderWidth: 0 }],
    },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '62%' },
});

new Chart(document.getElementById('accountCompositionChart'), {
    type: 'bar',
    data: {
        labels: accountChart.labels,
        datasets: [{ label: 'Akun', data: accountChart.values, backgroundColor: '#2563eb', borderRadius: 5 }],
    },
    options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf2f7' } },
            y: { grid: { display: false } },
        },
    },
});
</script>
@endpush
