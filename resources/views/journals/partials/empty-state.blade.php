<div class="empty-state">
    <div class="empty-illustration" aria-hidden="true">
        <span data-icon="journalPlus" class="app-icon" aria-hidden="true"></span>
    </div>
    <div class="empty-title">Belum ada jurnal.</div>
    <div class="empty-text">Mulai input transaksi pertama agar laporan dan dashboard terisi otomatis.</div>
    @if(auth()->user()->canManage('journal.create'))
        <a class="btn btn-primary btn-sm" href="{{ route('journals.create') }}"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Jurnal</a>
    @endif
</div>
