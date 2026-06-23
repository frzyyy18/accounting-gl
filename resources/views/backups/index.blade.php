@extends('layouts.app', ['title' => 'Backup & Restore'])

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h5>Backup Database</h5>
            <p class="text-muted">Buat file backup SQL dari database MySQL saat ini. File akan disimpan di storage aplikasi dan langsung diunduh.</p>
            <form method="post" action="{{ route('backups.download') }}">
                @csrf
                <button class="btn btn-primary">Buat & Download Backup</button>
            </form>
            <hr>
            <h6>Backup Tersimpan</h6>
            <table class="table table-sm">
                <thead><tr><th>File</th><th class="text-end">Ukuran</th><th>Waktu</th></tr></thead>
                <tbody>
                @forelse($files as $file)
                    <tr>
                        <td>{{ $file->getFilename() }}</td>
                        <td class="text-end">{{ number_format($file->getSize() / 1024, 1, ',', '.') }} KB</td>
                        <td>{{ date('d/m/Y H:i', $file->getMTime()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Belum ada file backup.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h5>Restore Database</h5>
            <div class="alert alert-warning">Restore akan menimpa data database sesuai isi file backup terenkripsi dari aplikasi ini. Gunakan hanya jika benar-benar memahami dampaknya.</div>
            <form method="post" action="{{ route('backups.restore') }}" enctype="multipart/form-data" onsubmit="return confirm('Restore database sekarang? Data saat ini bisa berubah.')">
                @csrf
                <div class="mb-3">
                    <label class="form-label">File Backup Terenkripsi (.sql.enc)</label>
                    <input type="file" name="backup_file" class="form-control" accept=".sql.enc" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ketik RESTORE untuk konfirmasi</label>
                    <input name="confirmation" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Saat Ini</label>
                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <button class="btn btn-danger">Restore Database</button>
            </form>
        </div>
    </div>
</div>
@endsection
