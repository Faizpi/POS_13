@extends('public.public-invoice-layout')

@section('title', 'Bukti Pembayaran - ' . ($pembayaran->custom_number ?? ''))

@section('content')
@php
    $invoiceUrl = url('invoice/pembayaran/' . $pembayaran->uuid);

    $statusClass = 'pending';
    $statusText = $pembayaran->status;
    if ($pembayaran->status == 'Approved') {
        $statusClass = 'approved';
        $statusText = 'Disetujui';
    } elseif ($pembayaran->status == 'Canceled') {
        $statusClass = 'canceled';
        $statusText = 'Dibatalkan';
    } elseif ($pembayaran->status == 'Pending') {
        $statusText = 'Menunggu';
    }
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0">Bukti Pembayaran</h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $pembayaran->custom_number }}</div>
    </div>

    <div class="invoice-body">
        <!-- Highlighted Payment Card -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 text-white p-6 rounded-2xl text-center mb-6 shadow-xl shadow-blue-500/30">
            <div class="text-[10px] uppercase tracking-widest font-bold opacity-80 mb-2">Jumlah Pembayaran</div>
            <div class="text-3xl font-extrabold tracking-tight">{{ format_rupiah($pembayaran->jumlah_bayar) }}</div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-receipt"></i> Detail Pembayaran</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $pembayaran->tgl_pembayaran->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu</span>
                <span class="value">{{ $pembayaran->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Metode</span>
                <span class="value text-blue-600 dark:text-blue-400">{{ $pembayaran->metode_pembayaran }}</span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge status-{{ $statusClass }}">{{ $statusText }}</span>
                </span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-file-invoice"></i> Referensi Invoice</div>
            @if($pembayaran->penjualan)
                <div class="info-row">
                    <span class="label">No. Invoice</span>
                    <span class="value">{{ $pembayaran->penjualan->custom_number }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Pelanggan</span>
                    <span class="value">{{ $pembayaran->penjualan->pelanggan ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Total Invoice</span>
                    <span class="value font-extrabold text-blue-600 dark:text-blue-400">{{ format_rupiah($pembayaran->penjualan->grand_total) }}</span>
                </div>
            @else
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value text-rose-500 italic font-normal">Invoice tidak tersedia</span>
                </div>
            @endif
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-user"></i> Dibuat Oleh</div>
            <div class="info-row">
                <span class="label">Nama</span>
                <span class="value">{{ $pembayaran->user->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $pembayaran->gudang->nama_gudang ?? '-' }}</span>
            </div>
            @if($pembayaran->approver)
                <div class="info-row">
                    <span class="label">Disetujui oleh</span>
                    <span class="value text-blue-600 dark:text-blue-400">{{ $pembayaran->approver->name }}</span>
                </div>
            @endif
        </div>

        @if($pembayaran->keterangan)
            <div class="info-card !bg-blue-50/50 dark:!bg-blue-950/20 !border-blue-100 dark:!border-blue-900/30">
                <div class="info-card-title text-blue-500 dark:text-blue-400"><i class="fas fa-sticky-note"></i> Keterangan</div>
                <p class="text-[13px] text-gray-600 dark:text-gray-300 leading-relaxed italic">{{ $pembayaran->keterangan }}</p>
            </div>
        @endif

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk verifikasi bukti ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        marketing@hibiscusefsya.com
    </div>
</div>
@endsection
