@extends('layouts.app', ['title' => 'Detail Jurnal'])

@section('content')
@php
    $canCancelJournal = in_array($journal->status, ['draft', 'submitted', 'rejected'], true)
        && ((int) $journal->created_by === (int) auth()->id() || auth()->user()->hasRole('super_admin'));
@endphp
<div class="card p-4">
    <div class="d-flex justify-content-between mb-3">
        <div>
            <h5>{{ $journal->reference_number ?: $journal->journal_number }}</h5>
            <div class="text-muted">{{ $journal->transaction_date->format('d/m/Y') }} | Cabang: {{ $journal->branch?->name ?: '-' }} | @include('journals.partials.status-badge', ['status' => $journal->status])</div>
        </div>
        <div class="d-flex gap-2">
            @if($previousJournal)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('journals.show',$previousJournal) }}">Sebelumnya</a>
            @endif
            @if($nextJournal)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('journals.show',$nextJournal) }}">Berikutnya</a>
            @endif
            @if(auth()->user()->canManage('journal.create'))
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('journals.duplicate',$journal) }}">Salin</a>
            @endif
            @if(!in_array($journal->status, ['posted','cancelled']))
                <a class="btn btn-outline-primary btn-sm" href="{{ route('journals.edit',$journal) }}">Edit</a>
            @endif
            @if(auth()->user()->canManage('journal.delete') && !in_array($journal->status, ['posted','cancelled'], true))
                <form method="post" action="{{ route('journals.destroy', $journal) }}">
                    @csrf @method('delete')
                    <button class="btn btn-outline-danger btn-sm" type="submit"><span data-icon="trash" class="app-icon me-1" aria-hidden="true"></span>Hapus</button>
                </form>
            @endif
            @if($journal->status === 'draft')
                <form method="post" action="{{ route('journals.submit',$journal) }}">@csrf <button class="btn btn-outline-info btn-sm">Submit</button></form>
            @endif
            @if($journal->status === 'submitted' && (int) $journal->submitted_by !== (int) auth()->id() && auth()->user()->canManage('journal.approve'))
                <form method="post" action="{{ route('journals.approve',$journal) }}">@csrf <button class="btn btn-outline-success btn-sm">Approve</button></form>
                <form method="post" action="{{ route('journals.reject',$journal) }}">@csrf <button class="btn btn-outline-danger btn-sm">Reject</button></form>
            @endif
            @if($journal->status === 'approved' && auth()->user()->canManage('journal.post'))
                <form method="post" action="{{ route('journals.post',$journal) }}">@csrf <button class="btn btn-primary btn-sm">Posting</button></form>
            @endif
            @if($canCancelJournal)
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel-journal>
                    Batalkan Jurnal
                </button>
            @endif
        </div>
    </div>
    <div class="mb-3"><div class="small text-muted">Deskripsi / Keterangan Transaksi</div><div>{{ $journal->description }}</div></div>
    @if($journal->status === 'cancelled')
        <div class="alert alert-secondary">
            <div class="fw-bold">Jurnal dibatalkan</div>
            <div class="small text-muted mb-1">
                {{ $journal->cancelled_at?->format('d/m/Y H:i') ?: '-' }}
            </div>
            <div>{{ $journal->cancellation_reason ?: '-' }}</div>
        </div>
    @endif
    @if($journal->attachment_path)
        <div class="mb-3"><a class="btn btn-outline-secondary btn-sm" href="{{ route('journals.attachment',$journal) }}"><span data-icon="attachment" class="app-icon me-1" aria-hidden="true"></span>{{ $journal->attachment_name ?: 'Bukti transaksi' }}</a></div>
    @endif
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Akun Terbesar</th><th class="money">Debit</th><th class="money">Kredit</th><th class="money">Aktivitas</th></tr></thead>
                    <tbody>
                    @forelse($accountSummary as $row)
                        <tr>
                            <td>{{ $row->account?->code }} - {{ $row->account?->name }}</td>
                            <td class="money">{{ rupiah($row->debit) }}</td>
                            <td class="money">{{ rupiah($row->credit) }}</td>
                            <td class="money">{{ rupiah($row->activity) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Belum ada detail akun.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <table class="table"><thead><tr><th>Akun</th><th>Deskripsi</th><th class="money">Debit</th><th class="money">Kredit</th></tr></thead><tbody>@foreach($journal->details as $detail)<tr><td>{{ $detail->account->code }} - {{ $detail->account->name }}</td><td>{{ $detail->description }}</td><td class="money">{{ rupiah($detail->debit) }}</td><td class="money">{{ rupiah($detail->credit) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="2">Total</th><th class="money">{{ rupiah($journal->details->sum('debit')) }}</th><th class="money">{{ rupiah($journal->details->sum('credit')) }}</th></tr></tfoot></table>
</div>

@if($canCancelJournal)
<div class="modal fade" id="cancelJournalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('journals.cancel', $journal) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Batalkan Jurnal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Alasan Pembatalan</label>
                    <textarea name="cancellation_reason" class="form-control" rows="4" required minlength="10" placeholder="Tuliskan alasan pembatalan minimal 10 karakter.">{{ old('cancellation_reason') }}</textarea>
                </div>
                <div class="form-text">Jurnal yang dibatalkan tidak bisa diedit, dihapus, atau diposting.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-outline-secondary">Batalkan Jurnal</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@if($canCancelJournal)
@push('scripts')
<script>
document.querySelector('[data-cancel-journal]')?.addEventListener('click', () => {
    const modalElement = document.getElementById('cancelJournalModal');
    if (!modalElement) return;
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
@endpush
@endif
