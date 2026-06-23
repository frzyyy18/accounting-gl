<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Accounting GL' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-ui.css') }}?v={{ filemtime(public_path('css/app-ui.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/app-custom.css') }}?v={{ file_exists(public_path('css/app-custom.css')) ? filemtime(public_path('css/app-custom.css')) : time() }}" rel="stylesheet">
    <script>
        (() => {
            const theme = localStorage.getItem('accounting-gl:theme') || 'light';
            document.documentElement.dataset.theme = theme;
        })();
    </script>
</head>
<body class="{{ auth()->check() ? 'app-shell' : 'auth-shell' }}">
@php
    $flashToasts = collect();

    if (session('success')) {
        $flashToasts->push(['type' => 'success', 'message' => session('success')]);
    }

    if (session('error')) {
        $flashToasts->push(['type' => 'danger', 'message' => session('error')]);
    }

    if ($errors->any()) {
        foreach ($errors->all() as $message) {
            $flashToasts->push(['type' => 'danger', 'message' => $message]);
        }
    }
@endphp
<div class="page-loading" role="status" aria-live="polite" aria-hidden="true">
    <div class="page-loading-bar"></div>
    <div class="page-loading-label">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span>Memproses...</span>
    </div>
</div>
<div class="toast-container app-toast-container" aria-live="polite" aria-atomic="true"></div>
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                Data yang dihapus tidak dapat dikembalikan. Apakah Anda yakin?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteSubmit"><span data-icon="trash" class="app-icon me-1" aria-hidden="true"></span>Hapus</button>
            </div>
        </div>
    </div>
</div>
@auth
@php
    $currentFiscalPeriod = \App\Models\FiscalPeriod::where('company_id', auth()->user()->company_id)
        ->whereDate('start_date', '<=', now()->toDateString())
        ->whereDate('end_date', '>=', now()->toDateString())
        ->first();
    $navGroups = [
        'Utama' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
            ['route' => 'dashboards.tax', 'label' => 'Dashboard Pajak', 'icon' => 'calculator', 'permission' => 'dashboard.view'],
        ],
        'Master Data' => [
            ['route' => 'companies.index', 'label' => 'Profil Perusahaan', 'icon' => 'building', 'permission' => 'company.manage'],
            ['route' => 'branches.index', 'label' => 'Cabang', 'icon' => 'branch', 'permission' => 'branch.manage'],
            ['route' => 'fiscal-periods.index', 'label' => 'Periode Akuntansi', 'icon' => 'calendar', 'permission' => 'period.manage'],
            ['route' => 'accounts.index', 'label' => 'Daftar Akun', 'icon' => 'accountList', 'permission' => 'account.view'],
            ['route' => 'cash-banks.index', 'label' => 'Kas & Bank', 'icon' => 'wallet', 'permission' => 'cash_bank.view'],
        ],
        'Transaksi' => [
            ['route' => 'journals.index', 'label' => 'Jurnal Umum', 'icon' => 'journal', 'permission' => 'journal.view'],
            ['route' => 'cash-bank-transactions.index', 'label' => 'Mutasi Kas/Bank', 'icon' => 'receipt', 'permission' => 'cash_transaction.view'],
            ['route' => 'bank-reconciliations.index', 'label' => 'Rekonsiliasi Bank', 'icon' => 'check', 'permission' => 'bank_reconciliation.view'],
            ['route' => 'closing-entries.create', 'label' => 'Closing Entry', 'icon' => 'lock', 'permission' => 'closing.create'],
        ],
        'Laporan' => [
            ['route' => 'reports.ledger', 'label' => 'Buku Besar', 'icon' => 'book', 'permission' => 'report.view'],
            ['route' => 'reports.trial-balance', 'label' => 'Neraca Saldo', 'icon' => 'columns', 'permission' => 'report.view'],
            ['route' => 'reports.profit-loss', 'label' => 'Laba Rugi', 'icon' => 'graphUp', 'permission' => 'report.view'],
            ['route' => 'reports.balance-sheet', 'label' => 'Neraca', 'icon' => 'bank', 'permission' => 'report.view'],
            ['route' => 'reports.cash-flow', 'label' => 'Arus Kas', 'icon' => 'cashStack', 'permission' => 'report.view'],
            ['route' => 'reports.cash-flow-indirect', 'label' => 'Arus Kas Tidak Langsung', 'icon' => 'arrowLeftRight', 'permission' => 'report.view'],
            ['route' => 'reports.audit-trail', 'label' => 'Audit Trail', 'icon' => 'shield', 'permission' => 'audit_trail.view'],
        ],
        'Laporan Pajak' => [
            ['route' => 'tax-reports.summary', 'label' => 'Rekap Pajak', 'icon' => 'calculator', 'permission' => 'tax_report.view'],
            ['route' => 'tax-reports.reconciliation', 'label' => 'Rekonsiliasi Fiskal', 'icon' => 'calculator', 'permission' => 'tax_report.view'],
        ],
        'Pengaturan' => [
            ['route' => 'users.index', 'label' => 'User', 'icon' => 'user', 'permission' => 'user.manage'],
            ['route' => 'roles.index', 'label' => 'Role & Permission', 'icon' => 'role', 'permission' => 'role.manage'],
            ['route' => 'system-settings.edit', 'label' => 'Pengaturan Sistem', 'icon' => 'calculator', 'permission' => 'settings.manage'],
            ['route' => 'backups.index', 'label' => 'Backup & Restore', 'icon' => 'cloudDownload', 'permission' => 'backup.manage'],
        ],
    ];
    $navItemActive = function ($item) {
        $routeActive = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route']));
        if (! $routeActive) {
            return false;
        }

        return ! isset($item['param']) || request()->route('kind') === $item['param'] || request()->route('type') === $item['param'];
    };
    $activeNav = collect($navGroups)
        ->flatMap(fn ($items, $group) => collect($items)->map(fn ($item) => $item + ['group' => $group]))
        ->first($navItemActive);
@endphp
<div class="sidebar-backdrop" data-sidebar-close></div>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">GL</div>
        <div>
            <div class="brand-title">Accounting GL</div>
            <div class="brand-subtitle">General Ledger System</div>
        </div>
    </div>

    <div class="nav-scroll">
        @foreach($navGroups as $group => $items)
            @php
                $visibleItems = collect($items)->filter(fn ($item) => auth()->user()->canManage($item['permission']))->values();
                $isStaticGroup = in_array($group, ['Utama', 'Master Data'], true);
                $groupKey = \Illuminate\Support\Str::slug($group);
                $groupActive = $visibleItems->contains($navItemActive);
            @endphp
            @continue($visibleItems->isEmpty())
            <div class="nav-group {{ $isStaticGroup ? 'nav-group-static' : 'nav-group-collapsible' }} {{ $groupActive ? 'open' : '' }}" data-nav-group="{{ $groupKey }}">
                @if($isStaticGroup)
                    <div class="nav-label">{{ $group }}</div>
                @else
                    <button class="nav-group-toggle" type="button" data-nav-toggle aria-expanded="{{ $groupActive ? 'true' : 'false' }}">
                        <span>{{ $group }}</span>
                        <span data-icon="arrowDown" class="app-icon nav-group-chevron" aria-hidden="true"></span>
                    </button>
                @endif
                <div class="nav-group-items">
                @foreach($visibleItems as $item)
                    @php
                        $active = $navItemActive($item);
                        $href = isset($item['param']) ? route($item['route'], $item['param']) : route($item['route']);
                    @endphp
                    <a href="{{ $href }}" class="nav-link {{ $active ? 'active' : '' }}">
                        <span data-icon="{{ $item['icon'] }}" class="app-icon" aria-hidden="true"></span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
                </div>
            </div>
        @endforeach
    </div>
</aside>
<main class="main">
    <div class="print-report-header">
        <div>
            <div class="print-company">{{ auth()->user()->company?->name ?? 'Accounting GL' }}</div>
            <div class="print-title">{{ $title ?? 'Laporan' }}</div>
        </div>
        <div class="print-meta">
            <div>Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
            <div>Oleh: {{ auth()->user()->name }}</div>
        </div>
    </div>
    <nav class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn icon-btn d-lg-none" type="button" data-sidebar-toggle aria-label="Buka menu"><span data-icon="list" class="app-icon" aria-hidden="true"></span></button>
            <div>
                <div class="page-title">{{ $title ?? 'Dashboard' }}</div>
                <div class="breadcrumb-lite d-flex align-items-center gap-2 flex-wrap">
                    <span>{{ auth()->user()->company?->name ?? 'Semua Perusahaan' }}</span>
                    @if($currentFiscalPeriod)
                        <span class="period-chip period-chip-{{ $currentFiscalPeriod->status }}">{{ $currentFiscalPeriod->name }}: {{ strtoupper($currentFiscalPeriod->status) }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="topbar-actions">
            <form action="{{ route('search') }}" class="global-search d-none d-md-flex" role="search">
                <span data-icon="search" class="app-icon" aria-hidden="true"></span>
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari jurnal, akun, transaksi..." aria-label="Global search">
            </form>
            @if(auth()->user()->canManage('journal.create'))
                <a href="{{ route('journals.create') }}" class="btn btn-primary btn-sm no-print"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Jurnal</a>
            @endif
            <button class="btn icon-btn" type="button" data-theme-toggle aria-label="Aktifkan dark mode"><span data-icon="moon" class="app-icon" aria-hidden="true"></span></button>
            <a href="{{ route('mfa.setup') }}" class="btn icon-btn" aria-label="Multi-factor authentication"><span data-icon="shield" class="app-icon" aria-hidden="true"></span></a>
            <div class="dropdown user-menu">
                <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="d-none d-sm-block text-start">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">{{ auth()->user()->role?->label ?? auth()->user()->role?->name }}</div>
                    </div>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted">{{ auth()->user()->email }}</div>
                    </div>
                    <a class="dropdown-item" href="{{ route('mfa.setup') }}"><span data-icon="shield" class="app-icon me-2" aria-hidden="true"></span>Keamanan Akun</a>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger" type="submit"><span data-icon="logout" class="app-icon me-2" aria-hidden="true"></span>Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <div class="content-wrap">
        <nav class="app-breadcrumb no-print" aria-label="Breadcrumb">
            <ol>
                <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
                @if($activeNav && ($activeNav['route'] ?? null) !== 'dashboard')
                    <li>{{ $activeNav['group'] }}</li>
                    <li aria-current="page">{{ $title ?? $activeNav['label'] }}</li>
                @elseif(($title ?? 'Dashboard') !== 'Dashboard')
                    <li aria-current="page">{{ $title }}</li>
                @endif
            </ol>
        </nav>
        <form action="{{ route('search') }}" class="global-search mobile-search d-md-none mb-3" role="search">
            <span data-icon="search" class="app-icon" aria-hidden="true"></span>
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari jurnal, akun, transaksi..." aria-label="Global search">
        </form>
        @if($currentFiscalPeriod && in_array($currentFiscalPeriod->status, ['locked', 'closed'], true))
            <div class="period-warning no-print">
                <div class="period-warning-icon"><span data-icon="lockFill" class="app-icon" aria-hidden="true"></span></div>
                <div>
                    <div class="fw-bold">Periode {{ $currentFiscalPeriod->name }} sedang {{ strtoupper($currentFiscalPeriod->status) }}</div>
                    <div class="small">Input atau perubahan transaksi pada {{ $currentFiscalPeriod->start_date->format('d/m/Y') }} - {{ $currentFiscalPeriod->end_date->format('d/m/Y') }} dibatasi. Hubungi admin untuk membuka periode.</div>
                </div>
            </div>
        @endif
@endauth
        @yield('content')
@auth
    </div>
</main>
@endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.jsx'])
<script>
document.querySelectorAll('[data-sidebar-toggle]').forEach(button => {
    button.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
});
document.querySelectorAll('[data-sidebar-close]').forEach(button => {
    button.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
});

document.querySelectorAll('[data-nav-group]').forEach(group => {
    const toggle = group.querySelector('[data-nav-toggle]');
    if (!toggle) return;

    const storageKey = `accounting-gl:nav-group:${group.dataset.navGroup}`;
    const storedOpen = localStorage.getItem(storageKey);
    if (storedOpen !== null && !group.querySelector('.nav-link.active')) {
        group.classList.toggle('open', storedOpen === 'true');
        toggle.setAttribute('aria-expanded', storedOpen === 'true' ? 'true' : 'false');
    }

    toggle.addEventListener('click', () => {
        const open = !group.classList.contains('open');
        group.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        localStorage.setItem(storageKey, open ? 'true' : 'false');
    });
});

const loadingIndicator = document.querySelector('.page-loading');
let loadingTimer = null;

function showPageLoading(timeout = 9000) {
    if (!loadingIndicator) return;
    loadingIndicator.setAttribute('aria-hidden', 'false');
    document.body.classList.add('is-loading');
    clearTimeout(loadingTimer);
    loadingTimer = setTimeout(hidePageLoading, timeout);
}

function hidePageLoading() {
    if (!loadingIndicator) return;
    loadingIndicator.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('is-loading');
    clearTimeout(loadingTimer);
}

function setButtonLoading(button) {
    if (!button || button.dataset.loadingBound === 'true') return;
    button.dataset.loadingBound = 'true';
    button.classList.add('btn-loading');
    button.disabled = true;

    if (button.tagName === 'INPUT') {
        button.dataset.originalValue = button.value;
        button.value = 'Memproses...';
        return;
    }

    button.dataset.originalHtml = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span>Memproses...</span>';
}

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
        showPageLoading();
        setButtonLoading(form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]'));
    });
});

let pendingDeleteForm = null;
const deleteModalElement = document.getElementById('confirmDeleteModal');
const deleteModal = deleteModalElement ? new bootstrap.Modal(deleteModalElement) : null;

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        const methodInput = form.querySelector('input[name="_method"]');
        const isDelete = methodInput && methodInput.value.toLowerCase() === 'delete';

        if (!isDelete || form.dataset.confirmedDelete === 'true') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        hidePageLoading();
        pendingDeleteForm = form;
        deleteModal?.show();
    }, true);
});

document.getElementById('confirmDeleteSubmit')?.addEventListener('click', () => {
    if (!pendingDeleteForm) return;
    pendingDeleteForm.dataset.confirmedDelete = 'true';
    showPageLoading();
    setButtonLoading(document.getElementById('confirmDeleteSubmit'));
    pendingDeleteForm.submit();
});

document.querySelectorAll('a[href]').forEach(link => {
    link.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank' || link.hasAttribute('download')) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;

        showPageLoading();
    });
});

window.addEventListener('pageshow', hidePageLoading);

document.body.classList.add('page-enter');
requestAnimationFrame(() => document.body.classList.add('page-enter-active'));

const themeToggle = document.querySelector('[data-theme-toggle]');
function syncThemeToggle() {
    const dark = document.documentElement.dataset.theme === 'dark';
    if (!themeToggle) return;
    themeToggle.setAttribute('aria-label', dark ? 'Aktifkan light mode' : 'Aktifkan dark mode');
    themeToggle.innerHTML = `<span data-icon="${dark ? 'sun' : 'moon'}" class="app-icon" aria-hidden="true"></span>`;
    window.renderIcons?.(themeToggle);
}
themeToggle?.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    localStorage.setItem('accounting-gl:theme', next);
    syncThemeToggle();
});
syncThemeToggle();

function parseMoneyInput(value) {
    value = String(value || '').trim();
    if (!value) return 0;
    if (value.includes(',') && value.includes('.')) {
        value = value.replace(/\./g, '').replace(',', '.');
    } else if (value.includes(',')) {
        value = value.replace(',', '.');
    } else if (value.includes('.')) {
        value = value.replace(/\./g, '');
    }
    return Number(value.replace(/[^0-9.-]/g, '')) || 0;
}

function formatInputMoney(input) {
    const value = parseMoneyInput(input.value);
    input.value = value ? new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) : '';
}

function formatInputMoneyLive(input) {
    const raw = String(input.value || '');
    const hasDecimal = raw.includes(',');
    const parts = raw.replace(/[^\d,]/g, '').split(',');
    const integer = parts[0].replace(/^0+(?=\d)/, '');
    const decimal = parts.slice(1).join('').slice(0, 2);
    const formattedInteger = integer ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(integer)) : '';
    input.value = hasDecimal ? `${formattedInteger},${decimal}` : formattedInteger;
}

function bindMoneyInput(input) {
    if (input._moneyBound) return;
    input._moneyBound = true;
    input.addEventListener('input', () => formatInputMoneyLive(input));
    input.addEventListener('blur', () => formatInputMoney(input));
    input.addEventListener('focus', () => input.select());
}

window.bindMoneyInputs = function bindMoneyInputs(root = document) {
    root.querySelectorAll('[data-money-input]').forEach(bindMoneyInput);
};
window.bindMoneyInputs();

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-money-input]').forEach(input => {
            const value = parseMoneyInput(input.value);
            input.value = value ? value.toFixed(2) : '0';
        });
    }, true);
});

function enhanceSearchableSelect(select) {
    if (select.dataset.searchableBound === 'true') return;
    select.dataset.searchableBound = 'true';
    select.classList.add('visually-hidden');
    select.tabIndex = -1;

    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';
    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'form-control';
    input.autocomplete = 'off';
    input.placeholder = select.querySelector('option[value=""]')?.textContent?.trim() || 'Cari...';
    const menu = document.createElement('div');
    menu.className = 'searchable-select-menu';
    wrapper.append(input, menu);
    select.after(wrapper);

    const options = () => Array.from(select.options).filter(option => !option.disabled);
    const selected = () => select.selectedOptions[0];
    const syncInput = () => input.value = selected()?.value ? selected().textContent.trim() : '';
    const render = () => {
        const keyword = input.value.toLowerCase();
        menu.innerHTML = '';
        options()
            .filter(option => option.textContent.toLowerCase().includes(keyword))
            .slice(0, 40)
            .forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'searchable-select-option';
                button.textContent = option.textContent.trim();
                if (!option.value) button.classList.add('text-muted');
                button.addEventListener('mousedown', event => {
                    event.preventDefault();
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncInput();
                    wrapper.classList.remove('open');
                });
                menu.appendChild(button);
            });
    };

    input.addEventListener('focus', () => { wrapper.classList.add('open'); render(); });
    input.addEventListener('input', () => { wrapper.classList.add('open'); render(); });
    input.addEventListener('blur', () => setTimeout(() => {
        wrapper.classList.remove('open');
        if (!input.value.trim() && select.querySelector('option[value=""]')) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (!options().some(option => option.textContent.trim() === input.value.trim())) {
            syncInput();
        }
    }, 120));
    select.addEventListener('change', syncInput);
    syncInput();
}

window.bootSearchableSelects = function bootSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable]').forEach(enhanceSearchableSelect);
};
window.bootSearchableSelects();

function syncCashBankPreview() {
    const preview = document.querySelector('[data-cash-preview]');
    if (!preview) return;

    const type = preview.dataset.type;
    const amount = parseMoneyInput(document.querySelector('[name="amount"]')?.value);
    const cash = document.querySelector('[name="cash_bank_id"]')?.selectedOptions[0];
    const target = document.querySelector('[name="target_cash_bank_id"]')?.selectedOptions[0];
    const counter = document.querySelector('[name="counter_account_id"]')?.selectedOptions[0];
    const cashAccount = cash?.dataset.account || cash?.textContent?.trim() || '-';
    const targetAccount = target?.dataset.account || target?.textContent?.trim() || '-';
    const counterAccount = counter?.textContent?.trim() || '-';
    const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount);

    const set = (selector, value) => preview.querySelector(selector).textContent = value;
    if (type === 'cash_in' || type === 'bank_in') {
        set('[data-preview-account-a]', cashAccount);
        set('[data-preview-debit-a]', money);
        set('[data-preview-credit-a]', 'Rp 0,00');
        set('[data-preview-account-b]', counterAccount);
        set('[data-preview-debit-b]', 'Rp 0,00');
        set('[data-preview-credit-b]', money);
    } else if (type === 'cash_out') {
        set('[data-preview-account-a]', counterAccount);
        set('[data-preview-debit-a]', money);
        set('[data-preview-credit-a]', 'Rp 0,00');
        set('[data-preview-account-b]', cashAccount);
        set('[data-preview-debit-b]', 'Rp 0,00');
        set('[data-preview-credit-b]', money);
    } else {
        set('[data-preview-account-a]', targetAccount);
        set('[data-preview-debit-a]', money);
        set('[data-preview-credit-a]', 'Rp 0,00');
        set('[data-preview-account-b]', cashAccount);
        set('[data-preview-debit-b]', 'Rp 0,00');
        set('[data-preview-credit-b]', money);
    }
}

document.querySelectorAll('[data-cash-preview] select, [data-cash-preview] input, [name="cash_bank_id"], [name="target_cash_bank_id"], [name="counter_account_id"], [name="amount"]').forEach(element => {
    element.addEventListener('input', syncCashBankPreview);
    element.addEventListener('change', syncCashBankPreview);
});
syncCashBankPreview();

function tableKey(table, index) {
    return `accounting-gl:columns:${location.pathname}:${index}`;
}

document.querySelectorAll('table').forEach((table, tableIndex) => {
    if (table.dataset.noColumnToggle === 'true') return;
    const headers = Array.from(table.querySelectorAll('thead th'));
    if (headers.length < 2) return;

    const key = tableKey(table, tableIndex);
    const hidden = new Set(JSON.parse(localStorage.getItem(key) || '[]'));
    const toolbar = document.createElement('div');
    toolbar.className = 'table-tools no-print';
    toolbar.innerHTML = `
        <div class="d-inline-flex gap-2">
        <button class="btn btn-light btn-sm" type="button" data-table-export>
            <span data-icon="fileExcel" class="app-icon me-1" aria-hidden="true"></span>CSV
        </button>
        <div class="dropdown">
            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span data-icon="columns" class="app-icon me-1" aria-hidden="true"></span>Kolom
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2"></div>
        </div>
        </div>
    `;
    const menu = toolbar.querySelector('.dropdown-menu');

    headers.forEach((header, index) => {
        const label = header.textContent.trim() || `Kolom ${index + 1}`;
        const id = `${key}:${index}`.replace(/[^a-zA-Z0-9_-]/g, '-');
        menu.insertAdjacentHTML('beforeend', `
            <label class="dropdown-item column-toggle-item" for="${id}">
                <input class="form-check-input me-2" id="${id}" type="checkbox" ${hidden.has(index) ? '' : 'checked'} data-column-index="${index}">
                <span>${label}</span>
            </label>
        `);
    });

    table.parentElement?.insertBefore(toolbar, table);

    const applyColumns = () => {
        table.querySelectorAll('tr').forEach(row => {
            Array.from(row.children).forEach((cell, index) => {
                cell.classList.toggle('d-none', hidden.has(index));
            });
        });
        localStorage.setItem(key, JSON.stringify([...hidden]));
    };

    toolbar.addEventListener('change', event => {
        const input = event.target.closest('[data-column-index]');
        if (!input) return;
        const index = Number(input.dataset.columnIndex);
        input.checked ? hidden.delete(index) : hidden.add(index);
        applyColumns();
    });
    toolbar.querySelector('[data-table-export]')?.addEventListener('click', () => {
        const rows = Array.from(table.querySelectorAll('tr'))
            .map(row => Array.from(row.children)
                .filter((cell, index) => !hidden.has(index))
                .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`)
                .join(','))
            .filter(Boolean)
            .join('\n');
        const blob = new Blob([rows], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${document.title || 'table'}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    });
    applyColumns();
});

document.querySelectorAll('form').forEach((form, index) => {
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get' || form.matches('.global-search')) return;
    const key = `accounting-gl:filter:${location.pathname}:${index}`;

    if (!location.search && sessionStorage.getItem(key)) {
        location.replace(`${location.pathname}?${sessionStorage.getItem(key)}`);
        return;
    }

    form.addEventListener('submit', () => {
        const params = new URLSearchParams(new FormData(form));
        [...params.keys()].forEach(param => {
            if (!params.get(param)) params.delete(param);
        });
        sessionStorage.setItem(key, params.toString());
    });
});

const flashToasts = @json($flashToasts->values());
const toastStorageKey = 'accounting-gl:persistent-toasts';
const toastContainer = document.querySelector('.app-toast-container');

function toastIcon(type) {
    return type === 'success' ? 'success' : 'danger';
}

function toastTitle(type) {
    return type === 'success' ? 'Berhasil' : 'Perlu Perhatian';
}

function toastId(type, message) {
    let hash = 0;
    const raw = `${type}:${message}`;
    for (let index = 0; index < raw.length; index++) {
        hash = ((hash << 5) - hash) + raw.charCodeAt(index);
        hash |= 0;
    }
    return `toast-${Math.abs(hash)}`;
}

function readStoredToasts() {
    try {
        return JSON.parse(sessionStorage.getItem(toastStorageKey) || '[]');
    } catch {
        return [];
    }
}

function writeStoredToasts(toasts) {
    sessionStorage.setItem(toastStorageKey, JSON.stringify(toasts));
}

function removeStoredToast(id) {
    writeStoredToasts(readStoredToasts().filter(toast => toast.id !== id));
}

function renderToast(toast) {
    if (!toastContainer || document.getElementById(toast.id)) return;

    const element = document.createElement('div');
    element.id = toast.id;
    element.className = `toast app-toast app-toast-${toast.type}`;
    element.setAttribute('role', 'alert');
    element.setAttribute('aria-live', 'assertive');
    element.setAttribute('aria-atomic', 'true');
    element.innerHTML = `
        <div class="toast-header">
            <span data-icon="${toastIcon(toast.type)}" class="app-icon me-2" aria-hidden="true"></span>
            <strong class="me-auto">${toastTitle(toast.type)}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>
        <div class="toast-body"></div>
    `;
    element.querySelector('.toast-body').textContent = toast.message;
    element.addEventListener('hidden.bs.toast', () => {
        removeStoredToast(toast.id);
        element.remove();
    });
    toastContainer.appendChild(element);
    window.renderIcons?.(element);
    bootstrap.Toast.getOrCreateInstance(element, { autohide: false }).show();
}

function bootPersistentToasts() {
    const existing = readStoredToasts();
    const incoming = flashToasts.map(toast => ({
        id: toastId(toast.type, toast.message),
        type: toast.type,
        message: toast.message,
    }));
    const merged = [...existing];

    incoming.forEach(toast => {
        if (!merged.some(item => item.id === toast.id)) {
            merged.push(toast);
        }
    });

    writeStoredToasts(merged);
    merged.forEach(renderToast);
}

bootPersistentToasts();

let pendingNavigationPrefix = false;
document.addEventListener('keydown', event => {
    const target = event.target;
    const typing = target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.querySelector('.global-search input[name="q"]')?.focus();
        return;
    }

    if (typing || event.ctrlKey || event.metaKey || event.altKey) return;

    if (event.key.toLowerCase() === 'g') {
        pendingNavigationPrefix = true;
        setTimeout(() => pendingNavigationPrefix = false, 900);
        return;
    }

    if (!pendingNavigationPrefix) return;
    pendingNavigationPrefix = false;

    const routes = {
        d: @json(route('dashboard')),
        j: @json(route('journals.index')),
        a: @json(route('accounts.index')),
        b: @json(route('cash-bank-transactions.index')),
        r: @json(route('reports.ledger')),
    };
    const href = routes[event.key.toLowerCase()];
    if (href) {
        event.preventDefault();
        window.location.href = href;
    }
});
</script>
@stack('scripts')
</body>
</html>
