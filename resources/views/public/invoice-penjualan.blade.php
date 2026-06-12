@extends('public.public-invoice-layout')

@section('title', 'Invoice Penjualan - ' . ($penjualan->pelanggan ?? ''))

@section('content')
@php
    $nomorInvoice = $penjualan->nomor ?? $penjualan->custom_number ?? ('INV-' . $penjualan->id);
    $invoiceUrl = url('invoice/penjualan/' . $penjualan->uuid);

    $subtotal = $penjualan->items->sum('jumlah_baris');
    $kenaPajak = max(0, $subtotal - $penjualan->diskon_akhir);
    $pajakNominal = $kenaPajak * ($penjualan->tax_percentage / 100);

    // Status logic
    $statusClass = 'pending';
    $statusText = $penjualan->status;
    if ($penjualan->status == 'Lunas') {
        $statusClass = 'lunas';
        $statusText = 'Lunas';
    } elseif ($penjualan->status == 'Approved') {
        $statusClass = 'approved';
        $statusText = 'Belum Lunas';
    } elseif ($penjualan->status == 'Canceled') {
        $statusClass = 'canceled';
    }
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0">Invoice Penjualan</h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $nomorInvoice }}</div>
    </div>

    <div class="invoice-body">
        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-receipt"></i> Informasi Transaksi</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $penjualan->tgl_transaksi->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu</span>
                <span class="value">{{ $penjualan->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Jatuh Tempo</span>
                <span class="value font-bold text-rose-500 dark:text-rose-400">{{ $penjualan->tgl_jatuh_tempo ? $penjualan->tgl_jatuh_tempo->format('d M Y') : '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Syarat Bayar</span>
                <span class="value">{{ $penjualan->syarat_pembayaran ?? '-' }}</span>
            </div>
            @if($penjualan->no_referensi)
                <div class="info-row">
                    <span class="label">No. Ref</span>
                    <span class="value">{{ $penjualan->no_referensi }}</span>
                </div>
            @endif
            @if($penjualan->memo)
                <div class="info-row">
                    <span class="label">Memo</span>
                    <span class="value italic text-gray-400 font-normal">{{ $penjualan->memo }}</span>
                </div>
            @endif
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge status-{{ $statusClass }}">{{ $statusText }}</span>
                </span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-user"></i> Detail Pelanggan</div>
            <div class="info-row">
                <span class="label">Nama</span>
                <span class="value">{{ $penjualan->pelanggan }}</span>
            </div>
            @if($noTelepon)
            <div class="info-row">
                <span class="label">Kontak</span>
                <span class="value">{{ receipt_format_phone($noTelepon) }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="label">Sales</span>
                <span class="value text-blue-600 dark:text-blue-400">{{ $penjualan->user->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $penjualan->gudang->nama_gudang ?? '-' }}</span>
            </div>
        </div>

        <div class="items-section">
            <div class="items-title"><i class="fas fa-box"></i> Daftar Item</div>
            @foreach($penjualan->items as $item)
                <div class="item-card">
                    <div class="item-name">
                        <span>
                            {{ $item->produk->nama_produk }}
                            @if($item->produk->item_code)
                                <span class="item-code">({{ $item->produk->item_code }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="item-meta">
                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $item->kuantitas }} {{ $item->unit ?? 'Pcs' }} × {{ format_rupiah($item->harga_satuan) }}</span>
                        @if($item->diskon > 0)
                            <span class="bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400 px-1.5 py-0.5 rounded text-[10px] font-extrabold">-{{ $item->diskon }}%</span>
                        @endif
                    </div>
                    
                    @if($item->deskripsi)
                        <div class="item-desc">Ket: {{ $item->deskripsi }}</div>
                    @endif

                    <div class="flex items-center gap-2 mt-1 mb-3 text-[10px] text-gray-400 font-medium">
                        <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">Batch: {{ $item->batch_number ?? 'N/A' }}</span>
                        <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">Exp: {{ $item->expired_date ? $item->expired_date->format('d/m/Y') : 'N/A' }}</span>
                    </div>

                    <div class="item-total">
                        <span>Subtotal Item</span>
                        <span>{{ format_rupiah($item->jumlah_baris) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="totals-card">
            <div class="total-row">
                <span>Subtotal Keseluruhan</span>
                <span>{{ format_rupiah($subtotal) }}</span>
            </div>
            @if($penjualan->diskon_akhir > 0)
                <div class="total-row discount font-bold">
                    <span>Potongan Harga</span>
                    <span>- {{ format_rupiah($penjualan->diskon_akhir) }}</span>
                </div>
            @endif
            @if($penjualan->tax_percentage > 0)
                <div class="total-row">
                    <span>Pajak Transaksi ({{ $penjualan->tax_percentage }}%)</span>
                    <span>{{ format_rupiah($pajakNominal) }}</span>
                </div>
            @endif
            <div class="total-row grand">
                <span>Total Bayar</span>
                <span>{{ format_rupiah($penjualan->grand_total) }}</span>
            </div>
        </div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk verifikasi invoice ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        marketing@hibiscusefsya.com
    </div>
</div>
@endsection
