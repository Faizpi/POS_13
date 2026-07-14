<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan {{ ucfirst($exportType ?? 'all') }} - Hibiscus Efsya</title>
    <style>
        @page { margin: 14px 16px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #1f2937; margin: 0; }
        h1 { font-size: 15px; line-height: 1.2; margin: 0 0 3px; color: #9f1239; }
        h2 { font-size: 10px; margin: 12px 0 6px; color: #9f1239; }
        p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 4px; vertical-align: top; }
        th { background: #fff1f4; color: #9f1239; font-weight: bold; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .brand { border-bottom: 2px solid #9f1239; padding-bottom: 8px; margin-bottom: 10px; }
        .meta { color: #6b7280; font-size: 7px; }
        .summary-grid { margin-bottom: 10px; }
        .summary-card { border: 1px solid #fecdd3; background: #fff7f9; padding: 7px; }
        .summary-label { color: #6b7280; font-size: 7px; text-transform: uppercase; }
        .summary-value { color: #9f1239; font-size: 12px; font-weight: bold; margin-top: 2px; }
        .section { page-break-inside: avoid; margin-top: 10px; }
        .muted { color: #6b7280; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 8px; font-size: 6.5px; background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .badge-ok { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .badge-warn { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .badge-info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .compact td, .compact th { padding: 3px; font-size: 7px; }
        .item-line { margin-bottom: 2px; }
        .memo { color: #4b5563; font-size: 7px; }
        .empty { border: 1px dashed #fecdd3; background: #fff7f9; color: #6b7280; text-align: center; padding: 20px; margin-top: 12px; }
        .footer { position: fixed; bottom: -4px; left: 0; right: 0; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 6.5px; padding-top: 3px; }
    </style>
</head>
<body>
@php
    $transactions = collect($transactions ?? []);
    $exportType = $exportType ?? 'all';
    $reportTitle = $exportType === 'all' ? 'Semua Transaksi' : ucfirst($exportType);
    $money = function ($value) {
        return format_rupiah($value ?? 0);
    };
    $date = function ($value) {
        if (!$value) return '-';
        try { return \Carbon\Carbon::parse($value)->format('d/m/Y'); } catch (\Throwable $e) { return '-'; }
    };
    $dateTime = function ($item) use ($date) {
        $raw = $item->tgl_transaksi ?? $item->tgl_kunjungan ?? $item->tgl_pembayaran ?? $item->tgl_penerimaan ?? $item->created_at ?? null;
        $formatted = $date($raw);
        $timeSource = $item->created_at ?? null;
        if (!$timeSource) return $formatted;
        try { return $formatted . ' ' . \Carbon\Carbon::parse($timeSource)->format('H:i'); } catch (\Throwable $e) { return $formatted; }
    };
    $number = function ($item) {
        return $item->number ?? $item->custom_number ?? $item->nomor ?? '-';
    };
    $statusBadge = function ($status) {
        $status = $status ?: '-';
        if (in_array($status, ['Approved', 'Lunas', 'Selesai'], true)) return 'badge badge-ok';
        if (in_array($status, ['Pending', 'Draft', 'Belum Lunas'], true)) return 'badge badge-warn';
        return 'badge badge-info';
    };
    $itemsOf = function ($item) {
        return $item && $item->relationLoaded('items') && $item->items ? collect($item->items) : collect();
    };
    $kindOf = function ($item) {
        return $item ? class_basename($item) : '-';
    };
    $totalOf = function ($item) use ($kindOf) {
        return match ($kindOf($item)) {
            'Penjualan', 'Pembelian', 'Biaya' => (float) ($item->grand_total ?? 0),
            'Pembayaran' => (float) ($item->jumlah_bayar ?? 0),
            'Kunjungan' => 0.0,
            default => 0.0,
        };
    };
    $totalTextOf = function ($item) use ($kindOf, $totalOf, $money) {
        return $kindOf($item) === 'Kunjungan' ? '-' : $money($totalOf($item));
    };
    $detailValueTextOf = function ($item, $detail) use ($kindOf, $money) {
        if ($kindOf($item) === 'Kunjungan') {
            return '-';
        }

        return $money($detail->harga_satuan ?? $detail->jumlah ?? (($detail->jumlah_baris ?? 0) ?: (($detail->harga_satuan ?? 0) * ($detail->kuantitas ?? 0))));
    };
    $taxTextOf = function ($item) use ($kindOf) {
        return in_array($kindOf($item), ['Pembayaran', 'Kunjungan'], true)
            ? '-'
            : ($item->tax_percentage ?? 0) . '%';
    };
    $groupValue = function ($item, $field) {
        $value = $item->{$field} ?? null;
        return filled($value) ? (string) $value : '-';
    };
    $qtyOf = function ($detail) {
        return $detail->kuantitas ?? $detail->jumlah ?? 0;
    };
    $qtyTextOf = function ($item, $detail) use ($kindOf, $qtyOf) {
        return $kindOf($item) === 'Kunjungan'
            ? ($detail->kuantitas ?? $detail->qty ?? $detail->qty_diterima ?? '-')
            : $qtyOf($detail);
    };
    $lineAmountOf = function ($detail) {
        return $detail->jumlah_baris ?? $detail->jumlah ?? (($detail->harga_satuan ?? 0) * ($detail->kuantitas ?? 0));
    };
    $contactName = function ($item) {
        return $item->display_contact_name ?? $item->pelanggan ?? $item->penerima ?? optional($item->kontak)->nama ?? optional($item->penjualan)->pelanggan ?? '-';
    };
    $phone = function ($item) {
        return receipt_format_phone($item->no_telp_kontak ?? $item->no_telepon ?? $item->sales_no_telepon ?? optional($item->kontak)->no_telp ?? '');
    };
    $attachmentText = function ($item) {
        $paths = [];
        if (isset($item->lampiran_paths) && is_array($item->lampiran_paths)) $paths = $item->lampiran_paths;
        if (!empty($item->lampiran_path) && !in_array($item->lampiran_path, $paths, true)) $paths[] = $item->lampiran_path;
        if (!empty($item->bukti_bayar) && !in_array($item->bukti_bayar, $paths, true)) $paths[] = $item->bukti_bayar;
        return count($paths) ? implode(', ', array_map('basename', $paths)) : '-';
    };
    $totalAmount = $transactions->sum(function ($item) use ($totalOf) { return $totalOf($item); });
    $statusGroups = $transactions->groupBy(function ($item) use ($groupValue) { return $groupValue($item, 'status'); });
    $typeGroups = $transactions->groupBy(function ($item) use ($kindOf) { return $kindOf($item); });
    $jenisBiayaGroups = $transactions->groupBy(function ($item) use ($groupValue) { return $groupValue($item, 'jenis_biaya'); });
    $tujuanGroups = $transactions->groupBy(function ($item) use ($groupValue) { return $groupValue($item, 'tujuan'); });
    $metodePembayaranGroups = $transactions->groupBy(function ($item) use ($groupValue) { return $groupValue($item, 'metode_pembayaran'); });

    $cardThreeLabel = 'Status';
    $cardThreeValue = $statusGroups->count();
    $cardFourLabel = 'Gudang';
    $cardFourValue = $transactions->map(function ($item) { return optional($item->gudang)->nama_gudang; })->filter()->unique()->count();

    if ($exportType === 'all') {
        $cardThreeLabel = 'Tipe Transaksi';
        $cardThreeValue = $typeGroups->count();
    } elseif ($exportType === 'biaya') {
        $cardFourLabel = 'Jenis Biaya';
        $cardFourValue = $jenisBiayaGroups->count();
    } elseif ($exportType === 'kunjungan') {
        $cardFourLabel = 'Tujuan';
        $cardFourValue = $tujuanGroups->count();
    } elseif ($exportType === 'pembayaran') {
        $cardFourLabel = 'Metode Pembayaran';
        $cardFourValue = $metodePembayaranGroups->count();
    }
@endphp

<div class="brand">
    <h1>Hibiscus Efsya POS - Laporan {{ $reportTitle }}</h1>
    <p class="meta">
        Periode: {{ isset($dateFrom) ? $date($dateFrom) : '-' }} s/d {{ isset($dateTo) ? $date($dateTo) : '-' }}
        | Dicetak oleh: {{ $generatedBy ?? 'System' }}
        | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}
    </p>
</div>

<table class="summary-grid">
    <tr>
        <td class="summary-card" width="25%"><div class="summary-label">Jumlah Data</div><div class="summary-value">{{ $transactions->count() }}</div></td>
        <td class="summary-card" width="25%"><div class="summary-label">{{ $exportType === 'kunjungan' ? 'Nilai Transaksi' : 'Total Nilai' }}</div><div class="summary-value">{{ $exportType === 'kunjungan' ? '-' : $money($totalAmount) }}</div></td>
        <td class="summary-card" width="25%"><div class="summary-label">{{ $cardThreeLabel }}</div><div class="summary-value">{{ $cardThreeValue }}</div></td>
        <td class="summary-card" width="25%"><div class="summary-label">{{ $cardFourLabel }}</div><div class="summary-value">{{ $cardFourValue }}</div></td>
    </tr>
</table>

@if($transactions->isEmpty())
    <div class="empty">Tidak ada data untuk filter laporan ini.</div>
@else
    @if($exportType === 'penjualan')
        <table class="compact">
            <thead><tr><th width="3%">No</th><th width="9%">No. Transaksi</th><th width="9%">Tanggal</th><th width="8%">Sales</th><th width="10%">Pelanggan / Telp</th><th width="7%">Gudang</th><th width="6%">Status</th><th width="18%">Item</th><th width="6%">Qty</th><th width="7%">Harga</th><th width="6%">Diskon</th><th width="6%">Pajak</th><th width="8%">Grand Total</th><th width="7%">Memo/Lampiran</th></tr></thead>
            <tbody>
            @foreach($transactions as $item)
                @php $rows = $itemsOf($item); @endphp
                @forelse($rows as $idx => $detail)
                    <tr>
                        @if($idx === 0)
                            <td rowspan="{{ max($rows->count(), 1) }}">{{ $loop->parent->iteration }}</td><td rowspan="{{ $rows->count() }}">{{ $number($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $dateTime($item) }}</td><td rowspan="{{ $rows->count() }}">{{ optional($item->user)->name ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ $contactName($item) }}<br><span class="muted">{{ $phone($item) }}</span></td><td rowspan="{{ $rows->count() }}">{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td rowspan="{{ $rows->count() }}"><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td>
                        @endif
                        <td>{{ optional($detail->produk)->nama_produk ?? '-' }}<br><span class="muted">{{ optional($detail->produk)->kategori ?? '' }}</span></td><td class="text-center">{{ $qtyOf($detail) }}</td><td class="text-right">{{ $money($detail->harga_satuan ?? 0) }}</td><td class="text-right">{{ $detail->diskon ?? 0 }}%</td>
                        @if($idx === 0)<td rowspan="{{ $rows->count() }}" class="text-center">{{ $item->tax_percentage ?? 0 }}%</td><td rowspan="{{ $rows->count() }}" class="text-right"><strong>{{ $money($item->grand_total ?? 0) }}</strong></td><td rowspan="{{ $rows->count() }}"><span class="memo">{{ $item->memo ?? '-' }}</span><br><span class="muted">{{ $attachmentText($item) }}</span></td>@endif
                    </tr>
                @empty
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ optional($item->user)->name ?? '-' }}</td><td>{{ $contactName($item) }}<br>{{ $phone($item) }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $item->status ?? '-' }}</td><td>-</td><td>-</td><td>-</td><td>-</td><td>{{ $item->tax_percentage ?? 0 }}%</td><td class="text-right">{{ $money($item->grand_total ?? 0) }}</td><td>{{ $item->memo ?? '-' }}<br>{{ $attachmentText($item) }}</td></tr>
                @endforelse
            @endforeach
            </tbody>
        </table>
    @elseif($exportType === 'pembelian')
        <table class="compact">
            <thead><tr><th>No</th><th>No. Pembelian</th><th>Tanggal</th><th>Pembuat</th><th>Gudang</th><th>Status</th><th>Item</th><th>Qty</th><th>Harga</th><th>Jumlah</th><th>Diskon</th><th>Pajak</th><th>Grand Total</th><th>Memo/Lampiran</th></tr></thead>
            <tbody>@foreach($transactions as $item) @php $rows = $itemsOf($item); @endphp @forelse($rows as $idx => $detail)<tr>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $loop->parent->iteration }}</td><td rowspan="{{ $rows->count() }}">{{ $number($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $dateTime($item) }}</td><td rowspan="{{ $rows->count() }}">{{ optional($item->user)->name ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td rowspan="{{ $rows->count() }}"><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td>@endif<td>{{ optional($detail->produk)->nama_produk ?? '-' }}<br><span class="muted">{{ optional($detail->produk)->kategori ?? '' }}</span></td><td class="text-center">{{ $qtyOf($detail) }}</td><td class="text-right">{{ $money($detail->harga_satuan ?? 0) }}</td><td class="text-right">{{ $money($lineAmountOf($detail)) }}</td><td class="text-right">{{ $detail->diskon ?? 0 }}%</td>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $item->tax_percentage ?? 0 }}%</td><td rowspan="{{ $rows->count() }}" class="text-right"><strong>{{ $money($item->grand_total ?? 0) }}</strong></td><td rowspan="{{ $rows->count() }}">{{ $item->memo ?? '-' }}<br><span class="muted">{{ $attachmentText($item) }}</span></td>@endif</tr>@empty<tr><td>{{ $loop->iteration }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ optional($item->user)->name ?? '-' }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $item->status ?? '-' }}</td><td colspan="5">-</td><td>{{ $item->tax_percentage ?? 0 }}%</td><td class="text-right">{{ $money($item->grand_total ?? 0) }}</td><td>{{ $item->memo ?? '-' }}<br>{{ $attachmentText($item) }}</td></tr>@endforelse @endforeach</tbody>
        </table>
    @elseif($exportType === 'biaya')
        <table class="compact">
            <thead><tr><th>No</th><th>No. Biaya</th><th>Tanggal</th><th>Pembuat</th><th>Jenis</th><th>Penerima / Telp</th><th>Gudang</th><th>Status</th><th>Kategori</th><th>Deskripsi</th><th>Jumlah</th><th>Pajak</th><th>Grand Total</th><th>Memo/Lampiran</th></tr></thead>
            <tbody>@foreach($transactions as $item) @php $rows = $itemsOf($item); @endphp @forelse($rows as $idx => $detail)<tr>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $loop->parent->iteration }}</td><td rowspan="{{ $rows->count() }}">{{ $number($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $dateTime($item) }}</td><td rowspan="{{ $rows->count() }}">{{ optional($item->user)->name ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ ucfirst($item->jenis_biaya ?? '-') }}<br><span class="muted">{{ $item->bayar_dari ?? '' }}</span></td><td rowspan="{{ $rows->count() }}">{{ $contactName($item) }}<br><span class="muted">{{ $phone($item) }}</span></td><td rowspan="{{ $rows->count() }}">{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td rowspan="{{ $rows->count() }}"><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td>@endif<td>{{ $detail->kategori ?? '-' }}</td><td>{{ $detail->deskripsi ?? '-' }}</td><td class="text-right">{{ $money($detail->jumlah ?? 0) }}</td>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $item->tax_percentage ?? 0 }}%</td><td rowspan="{{ $rows->count() }}" class="text-right"><strong>{{ $money($item->grand_total ?? 0) }}</strong></td><td rowspan="{{ $rows->count() }}">{{ $item->memo ?? '-' }}<br><span class="muted">{{ $attachmentText($item) }}</span></td>@endif</tr>@empty<tr><td>{{ $loop->iteration }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ optional($item->user)->name ?? '-' }}</td><td>{{ ucfirst($item->jenis_biaya ?? '-') }}</td><td>{{ $contactName($item) }}<br>{{ $phone($item) }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $item->status ?? '-' }}</td><td>-</td><td>-</td><td>-</td><td>{{ $item->tax_percentage ?? 0 }}%</td><td class="text-right">{{ $money($item->grand_total ?? 0) }}</td><td>{{ $item->memo ?? '-' }}<br>{{ $attachmentText($item) }}</td></tr>@endforelse @endforeach</tbody>
        </table>
    @elseif($exportType === 'kunjungan')
        <table class="compact">
            <thead><tr><th>No</th><th>No. Kunjungan</th><th>Tanggal</th><th>Sales</th><th>Kontak / Telp</th><th>Gudang</th><th>Tujuan</th><th>Status</th><th>Produk/Kategori</th><th>Qty</th><th>Memo/Lampiran</th></tr></thead>
            <tbody>@foreach($transactions as $item) @php $rows = $itemsOf($item); @endphp @forelse($rows as $idx => $detail)<tr>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $loop->parent->iteration }}</td><td rowspan="{{ $rows->count() }}">{{ $number($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $dateTime($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $item->sales_nama ?? optional($item->user)->name ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ $contactName($item) }}<br><span class="muted">{{ $phone($item) }}</span></td><td rowspan="{{ $rows->count() }}">{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ $item->tujuan ?? '-' }}</td><td rowspan="{{ $rows->count() }}"><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td>@endif<td>{{ optional($detail->produk)->nama_produk ?? '-' }}<br><span class="muted">{{ optional($detail->produk)->kategori ?? '' }}</span></td><td class="text-center">{{ $qtyTextOf($item, $detail) }}</td>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $item->memo ?? '-' }}<br><span class="muted">{{ $attachmentText($item) }}</span></td>@endif</tr>@empty<tr><td>{{ $loop->iteration }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ $item->sales_nama ?? optional($item->user)->name ?? '-' }}</td><td>{{ $contactName($item) }}<br>{{ $phone($item) }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $item->tujuan ?? '-' }}</td><td>{{ $item->status ?? '-' }}</td><td>-</td><td>-</td><td>{{ $item->memo ?? '-' }}<br>{{ $attachmentText($item) }}</td></tr>@endforelse @endforeach</tbody>
        </table>
    @elseif($exportType === 'pembayaran')
        <table class="compact">
            <thead><tr><th>No</th><th>No. Pembayaran</th><th>Tanggal</th><th>Pembuat</th><th>Gudang</th><th>Pelanggan / Telp</th><th>Invoice</th><th>Metode</th><th>Status</th><th>Jumlah Bayar</th><th>Keterangan/Lampiran</th></tr></thead>
            <tbody>@foreach($transactions as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ optional($item->user)->name ?? '-' }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $contactName($item) }}<br><span class="muted">{{ $phone($item) }}</span></td><td>{{ optional($item->penjualan)->nomor ?? optional($item->penjualan)->custom_number ?? '-' }}<br><span class="muted">{{ $money(optional($item->penjualan)->grand_total ?? 0) }}</span></td><td>{{ $item->metode_pembayaran ?? '-' }}</td><td><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td><td class="text-right"><strong>{{ $money($item->jumlah_bayar ?? 0) }}</strong></td><td>{{ $item->keterangan ?? '-' }}<br><span class="muted">{{ $attachmentText($item) }}</span></td></tr>@endforeach</tbody>
        </table>
    @else
        <table class="compact">
            <thead><tr><th>No</th><th>Tipe</th><th>Nomor</th><th>Tanggal</th><th>Pembuat/Sales</th><th>Kontak / Telp</th><th>Gudang</th><th>Status</th><th>Detail Item/Kategori</th><th>Qty</th><th>Harga/Jumlah</th><th>Pajak</th><th>Total/Bayar</th><th>Memo/Lampiran</th></tr></thead>
            <tbody>@foreach($transactions as $item) @php $rows = $itemsOf($item); @endphp @forelse($rows as $idx => $detail)<tr>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $loop->parent->iteration }}</td><td rowspan="{{ $rows->count() }}">{{ $kindOf($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $number($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $dateTime($item) }}</td><td rowspan="{{ $rows->count() }}">{{ $item->sales_nama ?? optional($item->user)->name ?? '-' }}</td><td rowspan="{{ $rows->count() }}">{{ $contactName($item) }}<br><span class="muted">{{ $phone($item) }}</span></td><td rowspan="{{ $rows->count() }}">{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td rowspan="{{ $rows->count() }}"><span class="{{ $statusBadge($item->status ?? null) }}">{{ $item->status ?? '-' }}</span></td>@endif<td>{{ optional($detail->produk)->nama_produk ?? $detail->kategori ?? $detail->deskripsi ?? '-' }}<br><span class="muted">{{ $detail->deskripsi ?? optional($detail->produk)->kategori ?? '' }}</span></td><td class="text-center">{{ $qtyTextOf($item, $detail) }}</td><td class="text-right">{{ $detailValueTextOf($item, $detail) }}</td>@if($idx === 0)<td rowspan="{{ $rows->count() }}">{{ $taxTextOf($item) }}</td><td rowspan="{{ $rows->count() }}" class="text-right"><strong>{{ $totalTextOf($item) }}</strong></td><td rowspan="{{ $rows->count() }}">{{ $item->memo ?? $item->keterangan ?? '-' }}<br><span class="muted">{{ $attachmentText($item) }}</span></td>@endif</tr>@empty<tr><td>{{ $loop->iteration }}</td><td>{{ $kindOf($item) }}</td><td>{{ $number($item) }}</td><td>{{ $dateTime($item) }}</td><td>{{ $item->sales_nama ?? optional($item->user)->name ?? '-' }}</td><td>{{ $contactName($item) }}<br>{{ $phone($item) }}</td><td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td><td>{{ $item->status ?? '-' }}</td><td>-</td><td>-</td><td>-</td><td>{{ $taxTextOf($item) }}</td><td class="text-right">{{ $totalTextOf($item) }}</td><td>{{ $item->memo ?? $item->keterangan ?? '-' }}<br>{{ $attachmentText($item) }}</td></tr>@endforelse @endforeach</tbody>
        </table>
    @endif

    <div class="section">
        <h2>Ringkasan Status</h2>
        <table class="compact">
            <thead><tr><th>Status</th><th class="text-right">Jumlah Data</th><th class="text-right">Total Nilai</th></tr></thead>
            <tbody>@forelse($statusGroups as $status => $group)<tr><td>{{ $status }}</td><td class="text-right">{{ $group->count() }}</td><td class="text-right">{{ $exportType === 'kunjungan' ? '-' : $money($group->sum(function ($item) use ($totalOf) { return $totalOf($item); })) }}</td></tr>@empty<tr><td colspan="3" class="text-center muted">Tidak ada data.</td></tr>@endforelse</tbody>
        </table>
    </div>

    @if($exportType === 'all')
        <div class="section"><h2>Ringkasan Tipe Transaksi</h2><table class="compact"><thead><tr><th>Tipe</th><th class="text-right">Jumlah Data</th><th class="text-right">Total Nilai</th></tr></thead><tbody>@forelse($typeGroups as $type => $group)<tr><td>{{ $type }}</td><td class="text-right">{{ $group->count() }}</td><td class="text-right">{{ $type === 'Kunjungan' ? '-' : $money($group->sum(function ($item) use ($totalOf) { return $totalOf($item); })) }}</td></tr>@empty<tr><td colspan="3" class="text-center muted">Tidak ada data.</td></tr>@endforelse</tbody></table></div>
    @endif
    @if($exportType === 'biaya')
        <div class="section"><h2>Ringkasan Jenis Biaya</h2><table class="compact"><thead><tr><th>Jenis Biaya</th><th class="text-right">Jumlah Data</th><th class="text-right">Total Nilai</th></tr></thead><tbody>@forelse($jenisBiayaGroups as $jenis => $group)<tr><td>{{ ucfirst($jenis) }}</td><td class="text-right">{{ $group->count() }}</td><td class="text-right">{{ $money($group->sum(function ($item) use ($totalOf) { return $totalOf($item); })) }}</td></tr>@empty<tr><td colspan="3" class="text-center muted">Tidak ada data.</td></tr>@endforelse</tbody></table></div>
    @endif
    @if($exportType === 'kunjungan')
        <div class="section"><h2>Ringkasan Tujuan Kunjungan</h2><table class="compact"><thead><tr><th>Tujuan</th><th class="text-right">Jumlah Kunjungan</th></tr></thead><tbody>@forelse($tujuanGroups as $tujuan => $group)<tr><td>{{ $tujuan }}</td><td class="text-right">{{ $group->count() }}</td></tr>@empty<tr><td colspan="2" class="text-center muted">Tidak ada data.</td></tr>@endforelse</tbody></table></div>
    @endif
    @if($exportType === 'pembayaran')
        <div class="section"><h2>Ringkasan Metode Pembayaran</h2><table class="compact"><thead><tr><th>Metode Pembayaran</th><th class="text-right">Jumlah Data</th><th class="text-right">Total Dibayar</th></tr></thead><tbody>@forelse($metodePembayaranGroups as $metode => $group)<tr><td>{{ $metode }}</td><td class="text-right">{{ $group->count() }}</td><td class="text-right">{{ $money($group->sum(function ($item) use ($totalOf) { return $totalOf($item); })) }}</td></tr>@empty<tr><td colspan="3" class="text-center muted">Tidak ada data.</td></tr>@endforelse</tbody></table></div>
    @endif
@endif

<div class="footer">Hibiscus Efsya POS - Laporan {{ $reportTitle }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</div>
</body>
</html>
