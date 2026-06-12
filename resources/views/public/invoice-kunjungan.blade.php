@extends('public.public-invoice-layout')

@section('title', 'Bukti Kunjungan - ' . ($kunjungan->sales_nama ?? ''))

@section('content')
@php
    $nomorInvoice = $kunjungan->nomor ?? $kunjungan->custom_number ?? ('VST-' . $kunjungan->id);
    $invoiceUrl = url('invoice/kunjungan/' . $kunjungan->uuid);
@endphp

<div class="invoice-container">
    <div class="invoice-header">
        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" onerror="this.style.display='none'" class="max-w-[140px] mb-3 mx-auto">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white m-0 tracking-tight">BUKTI KUNJUNGAN</h1>
        <div class="invoice-number text-[13px] text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $nomorInvoice }}</div>
    </div>

    <div class="invoice-body">
        <div class="text-center mb-5">
            @if($kunjungan->tujuan == 'Pemeriksaan Stock')
                <span class="status-badge bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-950/30 dark:text-sky-400 dark:border-sky-800/50 gap-2 py-1 px-3">
                    <i class="fas fa-clipboard-check text-[10px]"></i> {{ $kunjungan->tujuan }}
                </span>
            @elseif($kunjungan->tujuan == 'Penagihan')
                <span class="status-badge bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800/50 gap-2 py-1 px-3">
                    <i class="fas fa-hand-holding-usd text-[10px]"></i> {{ $kunjungan->tujuan }}
                </span>
            @else
                <span class="status-badge bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800/50 gap-2 py-1 px-3">
                    <i class="fas fa-handshake text-[10px]"></i> {{ $kunjungan->tujuan }}
                </span>
            @endif
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-info-circle"></i> Informasi Kunjungan</div>
            <div class="info-row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $kunjungan->tgl_kunjungan->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Waktu Buat</span>
                <span class="value">{{ $kunjungan->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    @if($kunjungan->status == 'Approved')
                        <span class="status-badge status-approved">Disetujui</span>
                    @elseif($kunjungan->status == 'Pending')
                        <span class="status-badge status-pending">Menunggu</span>
                    @else
                        <span class="status-badge status-canceled">Dibatalkan</span>
                    @endif
                </span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-user"></i> Pelanggan</div>
            <div class="info-row">
                <span class="label">Nama</span>
                <span class="value">{{ $kunjungan->sales_nama }}</span>
            </div>
            @if($kunjungan->sales_no_telepon)
                <div class="info-row">
                    <span class="label">Kontak</span>
                    <span class="value">{{ receipt_format_phone($kunjungan->sales_no_telepon) }}</span>
                </div>
            @endif
            @if($kunjungan->sales_alamat)
                <div class="info-row">
                    <span class="label">Alamat</span>
                    <span class="value italic font-normal">{{ $kunjungan->sales_alamat }}</span>
                </div>
            @endif
        </div>

        <div class="info-card">
            <div class="info-card-title"><i class="fas fa-warehouse"></i> Detail Petugas</div>
            <div class="info-row">
                <span class="label">Gudang</span>
                <span class="value">{{ $kunjungan->gudang->nama_gudang ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Pembuat</span>
                <span class="value">{{ $kunjungan->user->name }}</span>
            </div>
            @if($kunjungan->status != 'Pending' && $kunjungan->approver)
                <div class="info-row">
                    <span class="label">Approver</span>
                    <span class="value text-blue-600 dark:text-blue-400">{{ $kunjungan->approver->name }}</span>
                </div>
            @endif
            @if($kunjungan->koordinat)
                <div class="info-row">
                    <span class="label">Lokasi</span>
                    <span class="value">
                        <a href="https://www.google.com/maps?q={{ $kunjungan->koordinat }}" target="_blank"
                            class="text-blue-600 dark:text-blue-400 font-bold no-underline hover:underline inline-flex items-center gap-1">
                            {{ $kunjungan->koordinat }} <i class="fas fa-external-link-alt text-[8px]"></i>
                        </a>
                    </span>
                </div>
            @endif
        </div>

        @if($kunjungan->items && $kunjungan->items->count() > 0)
            <div class="items-section">
                <div class="items-title"><i class="fas fa-box"></i> Daftar Item Kunjungan</div>
                @foreach($kunjungan->items as $item)
                    @php
                        $tipeStok = $item->tipe_stok ?? 'penjualan';
                        if ($kunjungan->tujuan === 'Promo Gratis') {
                            $tipeStok = 'gratis';
                        } elseif ($kunjungan->tujuan === 'Promo Sample') {
                            $tipeStok = 'sample';
                        }
                    @endphp
                    <div class="item-card">
                        <div class="item-name">
                            <span>
                                {{ $item->produk->nama_produk ?? 'Item Hapus' }}
                                @if($item->produk->item_code ?? false)
                                    <span class="item-code">({{ $item->produk->item_code }})</span>
                                @endif
                            </span>
                            <div class="flex flex-col items-end gap-1">
                                @if($tipeStok == 'gratis')
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/50">Gratis</span>
                                @elseif($tipeStok == 'sample')
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/50">Sample</span>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/50">Penjualan</span>
                                @endif
                            </div>
                        </div>
                        <div class="item-meta">
                            <span class="font-bold text-gray-700 dark:text-gray-300">Qty: {{ $item->jumlah ?? 1 }} {{ $item->produk->satuan ?? 'Pcs' }}</span>
                        </div>

                        <div class="flex items-center gap-2 mt-1 mb-3 text-[10px] text-gray-400 font-medium">
                            <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">Batch: {{ $item->batch_number ?? 'N/A' }}</span>
                            <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">Exp: {{ $item->expired_date ? $item->expired_date->format('d/m/Y') : 'N/A' }}</span>
                        </div>

                        @if($item->keterangan)
                            <div class="item-desc">
                                Ket: {{ $item->keterangan }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($kunjungan->memo)
            <div class="info-card !bg-blue-50/50 dark:!bg-blue-950/20 !border-blue-100 dark:!border-blue-900/30">
                <div class="info-card-title text-blue-500 dark:text-blue-400"><i class="fas fa-sticky-note"></i> Memo / Catatan</div>
                <p class="text-[13px] text-gray-600 dark:text-gray-300 leading-relaxed italic">{{ $kunjungan->memo }}</p>
            </div>
        @endif

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Scan untuk melihat bukti kunjungan ini</p>
        </div>
    </div>

    <div class="invoice-footer">
        <strong>HIBISCUS EFSYA</strong>
        <div class="text-[10px] mt-1 text-gray-400 italic">Dokumen ini sah tanpa tanda tangan</div>
    </div>
</div>
@endsection
