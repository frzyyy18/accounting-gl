@extends('layouts.app', ['title' => 'Jurnal Umum / General Journal'])

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Daftar Jurnal</span>
        @if(auth()->user()->canManage('journal.create'))
            <a href="{{ route('journals.create') }}" class="btn btn-primary btn-sm"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Jurnal</a>
        @endif
    </div>
    <div class="p-3 border-bottom">
        <form class="row g-2 align-items-end">
            @if(auth()->user()->hasRole('super_admin'))
                <div class="col-md-3">
                    <label class="form-label">Perusahaan</label>
                    <select name="company_id" class="form-select">
                        <option value="">Semua Perusahaan</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2"><label class="form-label">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Cari</label><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="No. jurnal, voucher, deskripsi"></div>
            <div class="col-md-2"><label class="form-label">Cabang</label><select name="branch_id" class="form-select"><option value="">Semua Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->code }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua Status</option>@foreach(['draft','submitted','approved','rejected','posted'] as $status)<option @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-1"><button class="btn btn-outline-secondary w-100"><span data-icon="filter" class="app-icon me-1" aria-hidden="true"></span>Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover responsive-table">
            <thead><tr><th>@include('partials.sort-link', ['field' => 'transaction_date', 'label' => 'Tanggal'])</th>@if(auth()->user()->hasRole('super_admin'))<th>Perusahaan</th>@endif<th>@include('partials.sort-link', ['field' => 'branch', 'label' => 'Cabang'])</th><th>@include('partials.sort-link', ['field' => 'reference_number', 'label' => 'No. Voucher'])</th><th>@include('partials.sort-link', ['field' => 'description', 'label' => 'Deskripsi'])</th><th class="money">Nominal</th><th>@include('partials.sort-link', ['field' => 'status', 'label' => 'Status'])</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($journals as $journal)
                <tr class="clickable-row" data-href="{{ route('journals.show',$journal) }}" tabindex="0">
                    <td data-label="Tanggal">{{ $journal->transaction_date->format('d/m/Y') }}</td>
                    @if(auth()->user()->hasRole('super_admin'))<td data-label="Perusahaan">{{ $journal->company?->name }}</td>@endif
                    <td data-label="Cabang">{{ $journal->branch?->code }}</td>
                    <td data-label="No. Voucher" class="fw-semibold">{{ $journal->reference_number ?: $journal->journal_number }}</td>
                    <td data-label="Deskripsi">{{ $journal->description }}</td>
                    <td data-label="Nominal" class="money">{{ rupiah($journal->total_amount ?? 0) }}</td>
                    <td data-label="Status">@include('journals.partials.status-badge', ['status' => $journal->status])</td>
                    <td data-label="Aksi" class="text-end">
                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('journals.show',$journal) }}">Detail</a>
                            @if(auth()->user()->canManage('journal.create'))
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('journals.duplicate',$journal) }}">Salin</a>
                            @endif
                            @if($journal->status === 'draft' && auth()->user()->canManage('journal.create'))
                                <form method="post" action="{{ route('journals.submit',$journal) }}">@csrf <button class="btn btn-outline-info btn-sm">Submit</button></form>
                            @endif
                            @if($journal->status === 'submitted' && (int) $journal->submitted_by !== (int) auth()->id() && auth()->user()->canManage('journal.approve'))
                                <form method="post" action="{{ route('journals.approve',$journal) }}">@csrf <button class="btn btn-outline-success btn-sm">Approve</button></form>
                                <form method="post" action="{{ route('journals.reject',$journal) }}">@csrf <button class="btn btn-outline-danger btn-sm">Reject</button></form>
                            @endif
                            @if($journal->status === 'approved' && auth()->user()->canManage('journal.post'))
                                <form method="post" action="{{ route('journals.post',$journal) }}">@csrf <button class="btn btn-primary btn-sm">Posting</button></form>
                            @endif
                            @if(auth()->user()->canManage('journal.delete') && !in_array($journal->status, ['posted','cancelled'], true))
                                <form method="post" action="{{ route('journals.destroy', $journal) }}">
                                    @csrf @method('delete')
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><span data-icon="trash" class="app-icon me-1" aria-hidden="true"></span>Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('super_admin') ? 8 : 7 }}">@include('journals.partials.empty-state')</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $journals->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.clickable-row').forEach(row => {
    const open = () => {
        const href = row.dataset.href;
        if (href) window.location.href = href;
    };
    row.addEventListener('click', event => {
        if (event.target.closest('a,button,form,input,select,textarea')) return;
        open();
    });
    row.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        open();
    });
});
</script>
@endpush
