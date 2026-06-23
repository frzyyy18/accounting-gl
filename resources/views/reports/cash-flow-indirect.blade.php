@extends('layouts.app', ['title' => 'Arus Kas Metode Tidak Langsung'])

@section('content')
<div class="card p-3">
    <h5>Arus Kas Metode Tidak Langsung</h5>
    @include('reports.partials.filters')
    <table class="table table-bordered table-sm responsive-table">
        <tbody>
            <tr><th data-label="Komponen">Laba/Rugi Bersih</th><th data-label="Nilai" class="money">{{ rupiah($netIncome) }}</th></tr>
            <tr><th colspan="2">Penyesuaian Perubahan Modal Kerja</th></tr>
            @forelse($adjustments as $row)
                <tr><td data-label="Komponen">{{ $row->code }} - {{ $row->name }} <span class="text-muted">({{ $row->type }})</span></td><td data-label="Nilai" class="money">{{ rupiah($row->cash_effect) }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center text-muted">Tidak ada penyesuaian modal kerja.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th data-label="Komponen">Arus Kas Bersih dari Aktivitas Operasi</th><th data-label="Nilai" class="money">{{ rupiah($operatingCashFlow) }}</th></tr></tfoot>
    </table>
</div>
@if(($print ?? false) && request('export') === 'pdf')<script>window.print()</script>@endif
@endsection
