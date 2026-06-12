@extends('public.public-invoice-layout')

@section('title', (strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'Bukti Pemasukan' : 'Bukti Pengeluaran') . ' - ' . ($biaya->penerima ?? ''))

@section('content')
@php
    $nomorInvoice = $biaya->nomor ?? $biaya->custom_number ?? ('EXP-' . $biaya->id);
    $invoiceUrl = url('invoice/biaya/' . $biaya->uuid);

    $subtotal = $biaya->items->sum('jumlah');
    $pajakNominal = $subtotal * (($biaya->tax_percentage ?? 0) / 100);

    $statusClass = 'pending';
    $statusText = $biaya->status;
    if ($biaya->status == 'Approved') {
        $statusClass = 'approved';
        $statusText = 'Disetujui';
    } elseif ($biaya->status == 'Canceled') {
        $statusClass = 'canceled';
        $statusText = 'Dibatalkan';
    }
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0">
            {{ strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'Bukti Pemasukan' : 'Bukti Pengeluaran' }}
        </h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $nomorInvoice }}</div>
    </div>

    <div class="invoice-body">
        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-receipt"></i> Informasi Dokumen</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $biaya->tgl_transaksi->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu</span>
                <span class="value">{{ $biaya->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Pembayaran</span>
                <span class="value">{{ $biaya->cara_pembayaran ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Jenis</span>
                <span class="value font-medium">{{ strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'Biaya Masuk' : 'Biaya Keluar' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Bayar Dari</span>
                <span class="value">{{ $biaya->bayar_dari ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge status-{{ $statusClass }}">{{ $statusText }}</span>
                </span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-user"></i> Informasi Penerima</div>
            <div class="info-row">
                <span class="label">Penerima</span>
                <span class="value">{{ $biaya->penerima ?? '-' }}</span>
            </div>
            @if($biaya->alamat_penagihan)
                <div class="info-row">
                    <span class="label">Alamat</span>
                    <span class="value">{{ $biaya->alamat_penagihan }}</span>
                </div>
            @endif
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $biaya->gudang->nama_gudang ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Pembuat</span>
                <span class="value">{{ $biaya->user->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Disetujui</span>
                <span class="value">{{ $biaya->status != 'Pending' && $biaya->approver ? $biaya->approver->name : '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Tag</span>
                <span class="value">{{ $biaya->tag ?? '-' }}</span>
            </div>
            @if($biaya->koordinat)
                <div class="info-row">
                    <span class="label">Koordinat</span>
                    <span class="value">{{ $biaya->koordinat }}</span>
                </div>
            @endif
            @if($biaya->memo)
                <div class="info-row">
                    <span class="label">Memo</span>
                    <span class="value italic text-gray-400 font-normal">{{ $biaya->memo }}</span>
                </div>
            @endif
        </div>

        <div class="items-section">
            <div class="items-title"><i class="fas fa-file-invoice-dollar"></i> Detail Pengeluaran</div>
            @foreach($biaya->items as $item)
                <div class="item-card">
                    <div class="item-name !mb-2 font-bold text-gray-700 dark:text-gray-300">
                        {{ $item->kategori }}
                        @if($item->deskripsi)
                            <div class="item-desc text-[11px] italic text-gray-500 dark:text-gray-400 mt-1">{{ $item->deskripsi }}</div>
                        @endif
                    </div>
                    <div class="item-total">
                        <span class="text-[12px] font-bold text-gray-700 dark:text-gray-300">Jumlah</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ format_rupiah($item->jumlah) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="totals-card">
            <div class="total-row">
                <span>Subtotal</span>
                <span>{{ format_rupiah($subtotal) }}</span>
            </div>
            @if(($biaya->tax_percentage ?? 0) > 0)
                <div class="total-row">
                    <span>Pajak ({{ $biaya->tax_percentage }}%)</span>
                    <span>{{ format_rupiah($pajakNominal) }}</span>
                </div>
            @endif
            <div class="total-row grand">
                <span>Grand Total</span>
                <span>{{ format_rupiah($biaya->grand_total) }}</span>
            </div>
        </div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk melihat bukti ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        marketing@hibiscusefsya.com
    </div>
</div>
@endsection
