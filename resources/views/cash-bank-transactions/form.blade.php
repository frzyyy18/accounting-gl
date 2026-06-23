@extends('layouts.app', ['title' => \App\Models\CashBankTransaction::TYPES[$type]])

@section('content')
@php
    $flowDescriptions = [
        'cash_in' => [
            'title' => 'Kas masuk wajib diterima ke Kas Kecil.',
            'description' => 'Gunakan akun lawan Piutang atau Pendapatan. Setoran dari Kas Kecil ke rekening bank dicatat lewat Transfer Bank.',
        ],
        'bank_in' => [
            'title' => 'Bank masuk mencatat uang yang masuk rekening bank.',
            'description' => 'Jika uang berasal dari Terima Piutang, pilih sumber kredit seperti Kas Tagihan, Koin, atau Piutang Giro. Jurnalnya Debit Bank dan Kredit sumber tersebut.',
        ],
        'cash_out' => [
            'title' => 'Kas keluar mencatat pembayaran biaya, hutang, atau pajak.',
            'description' => 'Gunakan akun lawan Beban, Hutang Dagang, Hutang Pajak, PPN, atau PPh sesuai bukti transaksi.',
        ],
        'transfer' => [
            'title' => 'Transfer memindahkan saldo antar Kas/Bank.',
            'description' => 'Contoh: setoran sales dari Kas Kecil ke Bank, atau pemindahan antar rekening bank.',
        ],
    ];
    $flow = $flowDescriptions[$type];
@endphp
<div class="card p-4">
    <div class="alert alert-light border d-flex gap-2 align-items-start">
        <span data-icon="info" class="app-icon mt-1" aria-hidden="true"></span>
        <div>
            <div class="fw-semibold">{{ $flow['title'] }}</div>
            <div class="small text-muted">{{ $flow['description'] }}</div>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" action="{{ route('cash-bank-transactions.store',$type) }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Tanggal Transaksi</label><input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label class="form-label">Cabang Transaksi</label><select name="branch_id" class="form-select" data-searchable required><option value="">Pilih Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ $type === 'transfer' ? 'Dari Kas/Bank' : (in_array($type, ['cash_in', 'bank_in'], true) ? 'Masuk Ke' : 'Dibayar Dari') }}</label><select name="cash_bank_id" class="form-select" data-searchable required><option value="">Pilih Kas/Bank</option>@foreach($cashBanks as $cashBank)<option value="{{ $cashBank->id }}" data-account="{{ $cashBank->account?->code }} - {{ $cashBank->account?->name }}" @selected(old('cash_bank_id')==$cashBank->id)>{{ $cashBank->name }} - {{ ($cashBank->scope ?? 'company') === 'company' ? 'Perusahaan' : $cashBank->branch?->code }}</option>@endforeach</select>@if($type === 'cash_in')<div class="form-text">Kas Masuk hanya boleh masuk ke Kas Kecil.</div>@elseif($type === 'bank_in')<div class="form-text">Bank Masuk hanya boleh masuk ke rekening bank.</div>@endif</div>
            @if($type === 'transfer')
                <div class="col-md-4"><label class="form-label">Ke Kas/Bank</label><select name="target_cash_bank_id" class="form-select" data-searchable required><option value="">Pilih Tujuan</option>@foreach($cashBanks as $cashBank)<option value="{{ $cashBank->id }}" data-account="{{ $cashBank->account?->code }} - {{ $cashBank->account?->name }}" @selected(old('target_cash_bank_id')==$cashBank->id)>{{ $cashBank->name }} - {{ ($cashBank->scope ?? 'company') === 'company' ? 'Perusahaan' : $cashBank->branch?->code }}</option>@endforeach</select></div>
            @else
                <div class="col-md-4"><label class="form-label">{{ $type === 'bank_in' ? 'Sumber Kredit' : 'Akun Lawan' }}</label><select name="counter_account_id" class="form-select" data-searchable required><option value="">{{ $type === 'bank_in' ? 'Pilih Sumber Kredit' : 'Pilih Akun Lawan' }}</option>@foreach($counterAccounts as $account)<option value="{{ $account->id }}" @selected(old('counter_account_id')==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select><div class="form-text">@if($type === 'bank_in')Pilih Kas Tagihan/Koin/Piutang Giro/Piutang Dagang sesuai sumber uang yang masuk bank.@elseif($type === 'cash_in')Pilihan dibatasi ke akun Piutang/Pendapatan.@else Pilihan dibatasi ke akun Biaya/Hutang/Pajak. @endif</div></div>
            @endif
            <div class="col-md-3"><label class="form-label">Nominal</label><input type="text" inputmode="decimal" name="amount" class="form-control money" data-money-input value="{{ old('amount') }}" required></div>
            <div class="col-md-4"><label class="form-label">Nomor Referensi</label><input name="reference_number" class="form-control" value="{{ old('reference_number') }}"></div>
            <div class="col-md-8"><label class="form-label">Keterangan</label><input name="description" class="form-control" value="{{ old('description') }}"></div>
            <div class="col-md-4"><label class="form-label">Bukti Transaksi</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
        </div>
        <div class="journal-preview mt-4" data-cash-preview data-type="{{ $type }}">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <strong>Preview Jurnal Otomatis</strong>
                <span class="badge text-bg-light border">Posted saat disimpan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Akun</th><th class="money">Debit</th><th class="money">Kredit</th></tr></thead>
                    <tbody>
                        <tr><td data-preview-account-a>-</td><td class="money" data-preview-debit-a>Rp 0,00</td><td class="money" data-preview-credit-a>Rp 0,00</td></tr>
                        <tr><td data-preview-account-b>-</td><td class="money" data-preview-debit-b>Rp 0,00</td><td class="money" data-preview-credit-b>Rp 0,00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-info mt-4 mb-0">Transaksi wajib memilih cabang. Kas scope perusahaan dapat dipakai semua cabang, sedangkan bank cabang hanya valid untuk cabang yang sesuai. Transaksi otomatis membuat jurnal Posted.</div>
        <div class="mt-4"><button class="btn btn-primary">Posting Transaksi</button><a href="{{ route('cash-bank-transactions.index') }}" class="btn btn-light">Batal</a></div>
    </form>
</div>
@endsection
