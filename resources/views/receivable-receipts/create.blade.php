@extends('layouts.app', ['title' => 'Terima Piutang'])

@section('content')
<div class="card p-4">
        <div class="alert alert-light border d-flex gap-2 align-items-start">
            <span data-icon="info" class="app-icon mt-1" aria-hidden="true"></span>
            <div>
                <div class="fw-semibold">Input kode voucher dari kasir.</div>
                <div class="small text-muted">Isi baris debit seperti Kas Tagihan, Giro, Koin, atau akun lain. Lawan transaksi otomatis Piutang Dagang di sisi kredit.</div>
            </div>
        </div>
    <form method="post" action="{{ route('receivable-receipts.store') }}">
        @csrf
        @php $items = old('details', [['account_id' => $defaultDebitAccountId]]); @endphp
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Tanggal Voucher</label><input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label class="form-label">Cabang</label><select name="branch_id" class="form-select" data-searchable required><option value="">Pilih Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Kode Voucher Kasir</label><input name="voucher_code" class="form-control text-uppercase" value="{{ old('voucher_code') }}" required></div>
            <div class="col-md-3"><label class="form-label">Lawan Transaksi</label><select name="receivable_account_id" class="form-select" data-searchable required>@foreach($receivableAccounts as $account)<option value="{{ $account->id }}" @selected(old('receivable_account_id', $defaultReceivableAccountId)==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select><div class="form-text">Otomatis sisi Kredit.</div></div>
            <div class="col-md-12"><label class="form-label">Keterangan Voucher</label><input name="description" class="form-control" value="{{ old('description') }}" placeholder="Opsional"></div>
        </div>
        <div class="journal-preview mt-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <strong>Preview Jurnal Otomatis</strong>
                <span class="badge text-bg-light border">Posted saat disimpan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="receipt-items">
                    <thead><tr><th>Akun</th><th>Keterangan</th><th class="money">Debit</th><th class="money">Kredit</th><th></th></tr></thead>
                    <tbody>
                        @foreach($items as $index => $item)
                            <tr data-credit-line>
                                <td><select name="details[{{ $index }}][account_id]" class="form-select" data-searchable required>@foreach($debitAccounts as $account)<option value="{{ $account->id }}" @selected(($item['account_id'] ?? $defaultDebitAccountId)==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td>
                                <td><input name="details[{{ $index }}][description]" class="form-control" value="{{ $item['description'] ?? '' }}" placeholder="Opsional"></td>
                                <td><input type="text" inputmode="decimal" name="details[{{ $index }}][amount]" class="form-control money receipt-amount" data-money-input value="{{ $item['amount'] ?? '' }}" required></td>
                                <td class="money">-</td>
                                <td><button type="button" class="btn btn-outline-danger btn-sm remove-receipt-line" title="Hapus baris" aria-label="Hapus baris"><span data-icon="trash" class="app-icon" aria-hidden="true"></span></button></td>
                            </tr>
                        @endforeach
                        <tr data-receivable-credit-row>
                            <td id="receipt-credit-account">120.01 - Piutang Dagang</td>
                            <td>Otomatis dari total debit</td>
                            <td class="money">-</td>
                            <td class="money"><span id="receipt-total-credit">0,00</span></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-receipt-line"><span data-icon="add" class="app-icon me-1" aria-hidden="true"></span>Tambah Debit</button>
                <div class="journal-summary">
                    <div><span>Total Debit/Kredit</span><strong id="receipt-total">0,00</strong></div>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary"><span data-icon="save" class="app-icon me-1" aria-hidden="true"></span>Posting Voucher</button>
            <a href="{{ route('cash-bank-transactions.index') }}" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
let receiptIndex = document.querySelectorAll('#receipt-items tbody tr[data-credit-line]').length;
const receiptFormatMoney = value => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
const recalculateReceiptTotal = () => {
    const total = Array.from(document.querySelectorAll('.receipt-amount')).reduce((sum, input) => sum + (window.parseMoneyInput ? window.parseMoneyInput(input.value) : (parseFloat(input.value) || 0)), 0);
    document.getElementById('receipt-total').textContent = receiptFormatMoney(total);
    document.getElementById('receipt-total-credit').textContent = receiptFormatMoney(total);
    const receivable = document.querySelector('[name="receivable_account_id"]')?.selectedOptions[0]?.textContent || 'Piutang Dagang';
    document.getElementById('receipt-credit-account').textContent = receivable.trim();
};
document.getElementById('add-receipt-line').addEventListener('click', () => {
    const template = document.querySelector('#receipt-items tbody tr[data-credit-line]');
    const row = template ? template.cloneNode(true) : document.createElement('tr');
    if (! template) {
        row.setAttribute('data-credit-line', '');
        row.innerHTML = `<td><select name="details[${receiptIndex}][account_id]" class="form-select" data-searchable required>@foreach($debitAccounts as $account)<option value="{{ $account->id }}" @selected($defaultDebitAccountId==$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td><td><input name="details[${receiptIndex}][description]" class="form-control" placeholder="Opsional"></td><td><input type="text" inputmode="decimal" name="details[${receiptIndex}][amount]" class="form-control money receipt-amount" data-money-input required></td><td class="money">-</td><td><button type="button" class="btn btn-outline-danger btn-sm remove-receipt-line" title="Hapus baris" aria-label="Hapus baris"><span data-icon="trash" class="app-icon" aria-hidden="true"></span></button></td>`;
    }
    row.querySelectorAll('.searchable-select').forEach(element => element.remove());
    row.querySelectorAll('input,select').forEach(element => {
        element.name = element.name.replace(/\[\d+\]/, `[${receiptIndex}]`);
        if (element.tagName === 'INPUT') {
            element.value = '';
        }
    });
    row.querySelectorAll('select[data-searchable]').forEach(select => {
        select.dataset.searchableBound = 'false';
        select.classList.remove('visually-hidden');
        select.tabIndex = 0;
    });
    document.querySelector('[data-receivable-credit-row]').before(row);
    receiptIndex++;
    window.bootSearchableSelects?.(row);
    window.renderIcons?.(row);
});
document.getElementById('receipt-items').addEventListener('input', event => {
    if (event.target.matches('.receipt-amount')) recalculateReceiptTotal();
});
document.getElementById('receipt-items').addEventListener('click', event => {
    const button = event.target.closest('.remove-receipt-line');
    if (button && document.querySelectorAll('#receipt-items tbody tr').length > 1) {
        button.closest('tr').remove();
        recalculateReceiptTotal();
    }
});
document.querySelector('[name="receivable_account_id"]').addEventListener('change', recalculateReceiptTotal);
recalculateReceiptTotal();
</script>
@endpush
