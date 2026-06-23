<form class="card report-filter-card no-print">
    <div class="card-header">
        <div>
            <div class="card-title mb-0">Filter Laporan</div>
            <div class="card-subtitle">Pilih periode dan parameter laporan</div>
        </div>
    </div>
    <div class="card-body">
    <div class="row g-3 align-items-end report-filter-row">
    @if(isset($companies) && auth()->user()->hasRole('super_admin'))
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Perusahaan</label><select name="company_id" class="form-select" data-searchable>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($selectedCompanyId ?? request('company_id')) == $company->id)>{{ $company->code ? $company->code.' - ' : '' }}{{ $company->name }}</option>@endforeach</select></div>
    @endif
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Banding Dari</label><input type="date" name="compare_from" value="{{ request('compare_from') }}" class="form-control"></div>
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Banding Sampai</label><input type="date" name="compare_to" value="{{ request('compare_to') }}" class="form-control"></div>
    @isset($branches)
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Cabang</label><select name="branch_id" class="form-select" data-searchable><option value="">Semua Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
    @endisset
    @isset($accounts)
    <div class="col-xl-3 col-lg-4 col-md-6"><label class="form-label">Akun</label><select name="account_id" class="form-select" data-searchable><option value="">Semua Akun</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(request('account_id')==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
    @endisset
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['posted'=>'Posted','draft'=>'Draft','submitted'=>'Submitted'] as $key=>$label)<option value="{{ $key }}" @selected(request('status','posted')===$key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Urutan</label><select name="sort_direction" class="form-select"><option value="newest" @selected(($sortDirection ?? request('sort_direction', 'newest')) === 'newest')>Terbaru dulu</option><option value="oldest" @selected(($sortDirection ?? request('sort_direction')) === 'oldest')>Terlama dulu</option></select></div>
    <div class="col report-filter-actions">
        <button class="btn btn-primary"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Tampilkan</button>
        <button name="export" value="pdf" class="btn btn-outline-primary"><span data-icon="filePdf" class="app-icon me-1" aria-hidden="true"></span>Export PDF</button>
        <button name="export" value="excel" class="btn btn-outline-primary"><span data-icon="fileExcel" class="app-icon me-1" aria-hidden="true"></span>Export Excel</button>
    </div>
    </div>
    </div>
</form>
