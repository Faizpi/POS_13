@extends('public.public-invoice-layout')

@section('title', 'Penerimaan Barang - ' . ($penerimaan->custom_number ?? ''))

@section('content')
@php
    $invoiceUrl = url('invoice/penerimaan-barang/' . $penerimaan->uuid);

    $statusClass = 'pending';
    $statusText = $penerimaan->status;
    if ($penerimaan->status == 'Approved') {
        $statusClass = 'approved';
        $statusText = 'Disetujui';
    } elseif ($penerimaan->status == 'Canceled') {
        $statusClass = 'canceled';
        $statusText = 'Dibatalkan';
    } elseif ($penerimaan->status == 'Pending') {
        $statusText = 'Menunggu';
    }

    $totalDiterima = $penerimaan->items->sum('qty_diterima');
    $totalReject = $penerimaan->items->sum('qty_reject');
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0">Penerimaan Barang</h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $penerimaan->custom_number }}</div>
    </div>

    <div class="invoice-body">
        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-truck-loading text-blue-500 dark:text-blue-400"></i> Informasi Penerimaan</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $penerimaan->tgl_penerimaan->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu</span>
                <span class="value">{{ $penerimaan->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $penerimaan->gudang->nama_gudang ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge status-{{ $statusClass }}">{{ $statusText }}</span>
                </span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-file-invoice text-blue-500 dark:text-blue-400"></i> Referensi Pembelian</div>
            @if($penerimaan->pembelian)
                <div class="info-row">
                    <span class="label">Invoice</span>
                    <span class="value">{{ $penerimaan->pembelian->custom_number }}</span>
                </div>
            @else
                <div class="info-row">
                    <span class="label">Invoice</span>
                    <span class="value text-gray-400 italic">-</span>
                </div>
            @endif
        </div>

        <div class="items-section">
            <div class="items-title"><i class="fas fa-box text-blue-500 dark:text-blue-400"></i> Detail Barang</div>
            @foreach($penerimaan->items as $item)
                <div class="item-card">
                    <div class="item-name">
                        <span>
                            {{ $item->produk->nama_produk ?? '-' }}
                            @if($item->produk->kode_produk)
                                <span class="item-code">({{ $item->produk->kode_produk }})</span>
                            @endif
                        </span>
                        <div class="flex flex-col items-end gap-1">
                            <span class="text-emerald-600 dark:text-emerald-400 text-sm font-bold">
                                <i class="fas fa-check-circle text-[10px] mr-0.5"></i> {{ $item->qty_diterima }}
                            </span>
                            @if($item->qty_reject > 0)
                                <span class="text-rose-500 dark:text-rose-400 text-[10px] font-bold">
                                    <i class="fas fa-times-circle mr-0.5"></i> {{ $item->qty_reject }} reject
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                        @if($item->tipe_stok == 'gratis')
                            <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/50">Gratis</span>
                        @elseif($item->tipe_stok == 'sample')
                            <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/50">Sample</span>
                        @else
                            <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/50">Penjualan</span>
                        @endif

                        @if($item->batch_number)
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                <i class="fas fa-hashtag text-[9px] mr-0.5 opacity-60"></i>Batch {{ $item->batch_number }}
                            </span>
                        @endif
                        @if($item->expired_date)
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                <i class="far fa-clock text-[9px] mr-0.5 opacity-60"></i>Exp {{ $item->expired_date->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk melihat penerimaan ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        marketing@hibiscusefsya.com
    </div>
</div>
@endsection
