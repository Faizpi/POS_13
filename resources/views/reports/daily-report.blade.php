<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian - {{ $salesName ?? '' }} - {{ $date ?? '' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 18px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #9f1239; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 7px; text-align: left; vertical-align: top; }
        th { background: #fff1f4; color: #9f1239; font-weight: bold; }
        .text-right { text-align: right; }
        .summary { background: #fff7f8; border: 1px solid #f3d6dc; padding: 10px; border-radius: 6px; margin-bottom: 14px; }
        .summary span { display: inline-block; margin-right: 18px; margin-bottom: 4px; }
        .brand { border-bottom: 2px solid #9f1239; padding-bottom: 9px; margin-bottom: 12px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    @php
        $formatDate = function ($value, string $format = 'd/m/Y') {
            if (blank($value)) return '-';
            try { return \Carbon\Carbon::parse($value)->format($format); } catch (\Throwable $e) { return '-'; }
        };

        $money = fn ($value) => format_rupiah($value ?? 0);
        $pembayarans = $pembayarans ?? collect();
    @endphp

    <div class="brand">
        <h1>Hibiscus Efsya POS - Laporan Harian</h1>
        <p><strong>Sales:</strong> {{ $salesName ?? '-' }} | <strong>Tanggal:</strong> {{ $date ?? '-' }} | Dicetak: {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="summary">
        <strong>Ringkasan:</strong><br>
        <span>Penjualan: {{ $penjualans->count() }} ({{ $money($penjualans->sum('grand_total')) }})</span>
        <span>Pembelian: {{ $pembelians->count() }} ({{ $money($pembelians->sum('grand_total')) }})</span>
        <span>Biaya: {{ $biayas->count() }} ({{ $money($biayas->sum('grand_total')) }})</span>
        <span>Kunjungan: {{ $kunjungans->count() }}</span>
        <span>Pembayaran: {{ $pembayarans->count() }} ({{ $money($pembayarans->sum('jumlah_bayar')) }})</span>
    </div>

    @if($penjualans->isNotEmpty())
        <h2>Penjualan</h2>
        <table>
            <thead><tr><th>#</th><th>Nomor</th><th>Tanggal</th><th>Pelanggan</th><th>Gudang</th><th>Item</th><th class="text-right">Total</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($penjualans as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->nomor ?? $p->custom_number ?? '-' }}</td>
                        <td>{{ $formatDate($p->tgl_transaksi) }}</td>
                        <td>{{ $p->pelanggan ?? '-' }}</td>
                        <td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td>
                        <td>{{ $p->items->pluck('produk.nama_produk')->filter()->implode(', ') ?: '-' }}</td>
                        <td class="text-right">{{ $money($p->grand_total) }}</td>
                        <td>{{ $p->status ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($pembelians->isNotEmpty())
        <h2>Pembelian</h2>
        <table>
            <thead><tr><th>#</th><th>Nomor</th><th>Tanggal</th><th>Gudang</th><th>Item</th><th class="text-right">Total</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($pembelians as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->nomor ?? $p->custom_number ?? '-' }}</td>
                        <td>{{ $formatDate($p->tgl_transaksi) }}</td>
                        <td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td>
                        <td>{{ $p->items->pluck('produk.nama_produk')->filter()->implode(', ') ?: '-' }}</td>
                        <td class="text-right">{{ $money($p->grand_total) }}</td>
                        <td>{{ $p->status ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($biayas->isNotEmpty())
        <h2>Biaya</h2>
        <table>
            <thead><tr><th>#</th><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Penerima</th><th>Gudang</th><th>Item</th><th class="text-right">Total</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($biayas as $i => $b)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $b->nomor ?? $b->custom_number ?? '-' }}</td>
                        <td>{{ $formatDate($b->tgl_transaksi) }}</td>
                        <td>{{ $b->jenis_biaya ?? '-' }}</td>
                        <td>{{ $b->penerima ?? '-' }}</td>
                        <td>{{ optional($b->gudang)->nama_gudang ?? '-' }}</td>
                        <td>{{ $b->items->map(fn ($item) => trim(($item->kategori ?? '').' '.($item->deskripsi ?? '')))->filter()->implode(', ') ?: '-' }}</td>
                        <td class="text-right">{{ $money($b->grand_total) }}</td>
                        <td>{{ $b->status ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($kunjungans->isNotEmpty())
        <h2>Kunjungan</h2>
        <table>
            <thead><tr><th>#</th><th>Nomor</th><th>Tanggal</th><th>Tujuan</th><th>Kontak</th><th>No Telepon</th><th>Gudang</th><th>Item</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($kunjungans as $i => $k)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $k->nomor ?? $k->custom_number ?? '-' }}</td>
                        <td>{{ $formatDate($k->tgl_kunjungan) }}</td>
                        <td>{{ $k->tujuan ?? '-' }}</td>
                        <td>{{ optional($k->kontak)->nama ?? '-' }}</td>
                        <td>{{ receipt_format_phone(optional($k->kontak)->no_telp ?? '') }}</td>
                        <td>{{ optional($k->gudang)->nama_gudang ?? '-' }}</td>
                        <td>{{ $k->items->pluck('produk.nama_produk')->filter()->implode(', ') ?: '-' }}</td>
                        <td>{{ $k->status ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($pembayarans->isNotEmpty())
        <h2>Pembayaran</h2>
        <table>
            <thead><tr><th>#</th><th>Nomor</th><th>Tanggal</th><th>Pelanggan</th><th>Invoice</th><th>Gudang</th><th class="text-right">Jumlah Bayar</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($pembayarans as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->nomor ?? $p->custom_number ?? '-' }}</td>
                        <td>{{ $formatDate($p->tgl_pembayaran) }}</td>
                        <td>{{ optional($p->penjualan)->pelanggan ?? '-' }}</td>
                        <td>{{ optional($p->penjualan)->nomor ?? optional($p->penjualan)->custom_number ?? '-' }}</td>
                        <td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td>
                        <td class="text-right">{{ $money($p->jumlah_bayar) }}</td>
                        <td>{{ $p->status ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($penjualans->isEmpty() && $pembelians->isEmpty() && $biayas->isEmpty() && $kunjungans->isEmpty() && $pembayarans->isEmpty())
        <p class="muted">Tidak ada aktivitas harian untuk tanggal ini.</p>
    @endif
</body>
</html>
