@extends('public.public-invoice-layout')

@section('title', 'Permintaan Pembelian - ' . ($pembelian->staf_penyetuju ?? ''))

@section('content')
@php
    $nomorInvoice = $pembelian->nomor ?? $pembelian->custom_number ?? ('PR-' . $pembelian->id);
    $invoiceUrl = url('invoice/pembelian/' . $pembelian->uuid);

    $subtotal = $pembelian->items->sum('jumlah_baris');
    $kenaPajak = max(0, $subtotal - ($pembelian->diskon_akhir ?? 0));
    $pajakNominal = $kenaPajak * (($pembelian->tax_percentage ?? 0) / 100);

    $statusClass = 'pending';
    $statusText = $pembelian->status;
    if ($pembelian->status == 'Lunas') {
        $statusClass = 'lunas';
    } elseif ($pembelian->status == 'Approved') {
        $statusClass = 'approved';
    } elseif ($pembelian->status == 'Canceled') {
        $statusClass = 'canceled';
    }
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0">Permintaan Pembelian</h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $nomorInvoice }}</div>
    </div>

    <div class="invoice-body">
        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-receipt"></i> Informasi Dokumen</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $pembelian->tgl_transaksi->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu</span>
                <span class="value">{{ $pembelian->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Jatuh Tempo</span>
                <span class="value font-bold text-rose-500 dark:text-rose-400">{{ $pembelian->tgl_jatuh_tempo ? $pembelian->tgl_jatuh_tempo->format('d M Y') : '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Syarat Bayar</span>
                <span class="value">{{ $pembelian->syarat_pembayaran ?? '-' }}</span>
            </div>
            @if($pembelian->urgensi)
                <div class="info-row">
                    <span class="label">Urgensi</span>
                    <span class="value text-amber-600 dark:text-amber-400">{{ $pembelian->urgensi }}</span>
                </div>
            @endif
            @if($pembelian->tahun_anggaran)
                <div class="info-row">
                    <span class="label">Thn Anggaran</span>
                    <span class="value">{{ $pembelian->tahun_anggaran }}</span>
                </div>
            @endif
            @if($pembelian->memo)
                <div class="info-row">
                    <span class="label">Memo</span>
                    <span class="value italic text-gray-400 font-normal">{{ $pembelian->memo }}</span>
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
            <div class="info-card-title"><i class="fas fa-building"></i> Informasi Vendor</div>
            <div class="info-row">
                <span class="label">Vendor</span>
                <span class="value text-blue-600 dark:text-blue-400">{{ $pembelian->staf_penyetuju ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Pembuat</span>
                <span class="value">{{ $pembelian->user->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Disetujui</span>
                <span class="value">{{ $pembelian->status != 'Pending' && $pembelian->approver ? $pembelian->approver->name : '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $pembelian->gudang->nama_gudang ?? '-' }}</span>
            </div>
        </div>

        <div class="items-section">
            <div class="items-title"><i class="fas fa-box"></i> Item Pembelian</div>
            @foreach($pembelian->items as $item)
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
                        <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">Exp: {{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : 'N/A' }}</span>
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
            @if(($pembelian->diskon_akhir ?? 0) > 0)
                <div class="total-row discount font-bold">
                    <span>Potongan Harga</span>
                    <span>- {{ format_rupiah($pembelian->diskon_akhir) }}</span>
                </div>
            @endif
            @if(($pembelian->tax_percentage ?? 0) > 0)
                <div class="total-row">
                    <span>Pajak Transaksi ({{ $pembelian->tax_percentage }}%)</span>
                    <span>{{ format_rupiah($pajakNominal) }}</span>
                </div>
            @endif
            <div class="total-row grand">
                <span>Total Bayar</span>
                <span>{{ format_rupiah($pembelian->grand_total) }}</span>
            </div>
        </div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk verifikasi dokumen ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        marketing@hibiscusefsya.com
    </div>
</div>
@endsection
