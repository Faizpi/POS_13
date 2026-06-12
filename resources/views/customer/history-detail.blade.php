@extends('customer.layouts.app')

@section('title', 'Detail Transaksi #' . $penjualan->nomor)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="font-weight-bold mb-0">Detail Transaksi #{{ $penjualan->nomor }}</h4>
        <a href="{{ route('customer.history') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Informasi Transaksi</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="pl-0" style="width:35%"><strong>Nomor</strong></td><td>: {{ $penjualan->nomor }}</td></tr>
                        <tr><td class="pl-0"><strong>Tanggal</strong></td><td>: {{ $penjualan->tgl_transaksi->format('d M Y') }}</td></tr>
                        <tr><td class="pl-0"><strong>Gudang</strong></td><td>: {{ $penjualan->gudang->nama_gudang ?? '—' }}</td></tr>
                        <tr><td class="pl-0"><strong>Sales</strong></td><td>: {{ $penjualan->user->name ?? '—' }}</td></tr>
                        <tr><td class="pl-0"><strong>Syarat Bayar</strong></td><td>: {{ $penjualan->syarat_pembayaran }}</td></tr>
                        @if($penjualan->tgl_jatuh_tempo)
                        <tr><td class="pl-0"><strong>Jatuh Tempo</strong></td><td>: {{ $penjualan->tgl_jatuh_tempo->format('d M Y') }}</td></tr>
                        @endif
                        <tr>
                            <td class="pl-0"><strong>Status</strong></td>
                            <td>:
                                @if($penjualan->status === 'Lunas') <span class="badge badge-success">Lunas</span>
                                @elseif($penjualan->status === 'Approved') <span class="badge badge-info">Belum Lunas</span>
                                @elseif($penjualan->status === 'Pending') <span class="badge badge-warning">Pending</span>
                                @elseif($penjualan->status === 'Rejected') <span class="badge badge-danger">Ditolak</span>
                                @else <span class="badge badge-secondary">{{ $penjualan->status }}</span> @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-calculator mr-1"></i> Ringkasan Total</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="pl-0"><strong>Subtotal</strong></td><td class="text-right">Rp {{ number_format($penjualan->items->sum('jumlah_baris'), 0, ',', '.') }}</td></tr>
                        @if($penjualan->diskon_akhir > 0)
                        <tr><td class="pl-0"><strong>Diskon</strong></td><td class="text-right" style="color:var(--portal-danger)">- Rp {{ number_format($penjualan->diskon_akhir, 0, ',', '.') }}</td></tr>
                        @endif
                        @if($penjualan->tax_percentage > 0)
                        <tr><td class="pl-0"><strong>Pajak ({{ $penjualan->tax_percentage }}%)</strong></td><td class="text-right">Rp {{ number_format(($penjualan->items->sum('jumlah_baris') - $penjualan->diskon_akhir) * ($penjualan->tax_percentage / 100), 0, ',', '.') }}</td></tr>
                        @endif
                        <tr style="border-top:2px solid var(--portal-border)">
                            <td class="pl-0"><strong class="text-primary">Grand Total</strong></td>
                            <td class="text-right font-weight-bold" style="color:var(--portal-primary);font-size:1.05rem">Rp {{ number_format($penjualan->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-box mr-1"></i> Rincian Produk</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Harga</th>
                            <th class="text-center">Disc%</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($penjualan->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->produk->nama_produk ?? '—' }}</strong>
                                @if($item->batch_number)<br><small class="text-muted">Batch: {{ $item->batch_number }}</small>@endif
                            </td>
                            <td class="text-center">{{ $item->kuantitas }} {{ $item->unit }}</td>
                            <td class="text-right">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->diskon }}%</td>
                            <td class="text-right font-weight-bold">Rp {{ number_format($item->jumlah_baris, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
