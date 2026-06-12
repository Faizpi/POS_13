<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Tagihan dan Cash</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1f2937;
            margin: 18px;
        }

        .header {
            border-bottom: 2px solid #dc2626;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header h2 {
            margin: 0 0 4px 0;
            color: #dc2626;
            font-size: 17px;
        }

        .meta {
            font-size: 9px;
            color: #4b5563;
            line-height: 1.5;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 14px 0;
        }

        .summary td {
            border: 1px solid #e5e7eb;
            padding: 7px 8px;
            background: #fff7ed;
        }

        .summary .label {
            color: #7c2d12;
            font-size: 8px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .summary .value {
            color: #111827;
            font-size: 11px;
            font-weight: bold;
            margin-top: 3px;
        }

        .section-title {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
            padding: 6px 8px;
            margin: 12px 0 6px 0;
            font-size: 11px;
            font-weight: bold;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .data-table th {
            background: #f9fafb;
            color: #374151;
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
        }

        .data-table td {
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            vertical-align: top;
            font-size: 8px;
        }

        .data-table tr:nth-child(even) {
            background: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row td {
            background: #fee2e2;
            font-weight: bold;
            color: #7f1d1d;
        }

        .empty {
            text-align: center;
            border: 1px solid #e5e7eb;
            padding: 10px;
            color: #6b7280;
            font-style: italic;
            margin-bottom: 10px;
        }

        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            color: #fff;
            font-size: 7px;
        }

        .badge-success {
            background: #16a34a;
        }

        .badge-primary {
            background: #2563eb;
        }

        .badge-warning {
            background: #d97706;
        }

        .lampiran-thumb {
            max-width: 105px;
            max-height: 80px;
            border: 1px solid #d1d5db;
            margin: 2px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    @php
        $tanggalMulai = $tanggalMulai ?? $tanggal;
        $tanggalSelesai = $tanggalSelesai ?? $tanggal;
        $cashHariIni = $cashHariIni ?? collect();
        $jatuhTempoBelumTerbayar = $jatuhTempoBelumTerbayar ?? collect();
        $totalCashHariIni = $totalCashHariIni ?? 0;
        $totalSisaCashHariIni = $totalSisaCashHariIni ?? 0;
        $totalJatuhTempo = $totalJatuhTempo ?? 0;
        $totalBelumLunas = $totalSisaCashHariIni + $totalJatuhTempo;
        $isSingleDate = $tanggalMulai->toDateString() === $tanggalSelesai->toDateString();
        $rentangLabel = $isSingleDate
            ? $tanggalMulai->format('d F Y')
            : $tanggalMulai->format('d F Y') . ' - ' . $tanggalSelesai->format('d F Y');
        $cashTitle = $isSingleDate && $tanggalMulai->isToday()
            ? 'Cash Hari Ini'
            : ($isSingleDate ? 'Cash Tanggal ' . $tanggalMulai->format('d/m/Y') : 'Cash Dalam Rentang');
        $jatuhTempoTitle = 'Tagihan Jatuh Tempo s/d ' . $tanggalSelesai->format('d/m/Y');
    @endphp

    <div class="header">
        <h2>Laporan Tagihan dan Cash Penjualan</h2>
        <div class="meta">
            <strong>Rentang Waktu:</strong> {{ $rentangLabel }} |
            <strong>Dibuat oleh:</strong> {{ $generatedBy }} |
            <strong>Dicetak:</strong> {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <table class="summary">
        <tr>
            <td width="25%">
                <div class="label">{{ $cashTitle }}</div>
                <div class="value">{{ format_rupiah($totalCashHariIni) }}</div>
            </td>
            <td width="25%">
                <div class="label">Sisa Cash Belum Lunas</div>
                <div class="value">{{ format_rupiah($totalSisaCashHariIni) }}</div>
            </td>
            <td width="25%">
                <div class="label">{{ $jatuhTempoTitle }}</div>
                <div class="value">{{ format_rupiah($totalJatuhTempo) }}</div>
            </td>
            <td width="25%">
                <div class="label">Total Belum Lunas</div>
                <div class="value">{{ format_rupiah($totalBelumLunas) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">{{ $cashTitle }} ({{ $cashHariIni->count() }} data)</div>
    @if($cashHariIni->isEmpty())
        <div class="empty">Tidak ada transaksi cash pada rentang ini.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="12%">Nomor Invoice</th>
                    <th width="9%">Tgl Transaksi</th>
                    <th width="15%">Nama Toko</th>
                    <th width="11%">Lokasi</th>
                    <th width="10%" class="text-right">Total Cash</th>
                    <th width="10%" class="text-right">Dibayar</th>
                    <th width="10%" class="text-right">Sisa</th>
                    <th width="8%">Status</th>
                    <th width="7%">Koordinat</th>
                    <th width="5%">Lampiran</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cashHariIni as $index => $item)
                    @php
                        $invoiceNomor = $item->nomor ?? $item->custom_number ?? '-';
                        $lampiranPaths = $item->lampiran_paths ?? [];
                        if ($item->lampiran_path && !in_array($item->lampiran_path, $lampiranPaths)) {
                            $lampiranPaths[] = $item->lampiran_path;
                        }
                        $imagePaths = collect($lampiranPaths)->filter(fn($path) => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path));
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $invoiceNomor }}</td>
                        <td>{{ $item->tgl_transaksi ? $item->tgl_transaksi->format('d/m/Y') : '-' }}</td>
                        <td>{{ $item->pelanggan ?? '-' }}</td>
                        <td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td>
                        <td class="text-right">{{ format_rupiah($item->grand_total) }}</td>
                        <td class="text-right">{{ format_rupiah($item->total_bayar_approved) }}</td>
                        <td class="text-right">{{ format_rupiah($item->jumlah_tagihan) }}</td>
                        <td>
                            @if($item->status == 'Lunas')
                                <span class="badge badge-primary">Lunas</span>
                            @elseif($item->status == 'Approved')
                                <span class="badge badge-success">Approved</span>
                            @else
                                <span class="badge badge-warning">{{ $item->status }}</span>
                            @endif
                        </td>
                        <td>{{ $item->koordinat ?? '-' }}</td>
                        <td>
                            @if($imagePaths->count() > 0)
                                @foreach($imagePaths as $lampiran)
                                    @php $fullPath = public_path('storage/' . $lampiran); @endphp
                                    @if(file_exists($fullPath))
                                        <img src="{{ $fullPath }}" class="lampiran-thumb" alt="Lampiran">
                                    @endif
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="text-right">Total Cash</td>
                    <td class="text-right">{{ format_rupiah($totalCashHariIni) }}</td>
                    <td></td>
                    <td class="text-right">{{ format_rupiah($totalSisaCashHariIni) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="section-title">{{ $jatuhTempoTitle }} ({{ $jatuhTempoBelumTerbayar->count() }} data)</div>
    @if($jatuhTempoBelumTerbayar->isEmpty())
        <div class="empty">Tidak ada tagihan jatuh tempo yang belum lunas sampai tanggal akhir rentang ini.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="11%">Nomor Invoice</th>
                    <th width="8%">Tgl Transaksi</th>
                    <th width="8%">Jatuh Tempo</th>
                    <th width="8%">Syarat</th>
                    <th width="13%">Nama Toko</th>
                    <th width="10%">Lokasi</th>
                    <th width="9%" class="text-right">Total</th>
                    <th width="9%" class="text-right">Dibayar</th>
                    <th width="9%" class="text-right">Sisa</th>
                    <th width="6%">Koordinat</th>
                    <th width="6%">Lampiran</th>
                </tr>
            </thead>
            <tbody>
                @foreach($jatuhTempoBelumTerbayar as $index => $item)
                    @php
                        $invoiceNomor = $item->nomor ?? $item->custom_number ?? '-';
                        $lampiranPaths = $item->lampiran_paths ?? [];
                        if ($item->lampiran_path && !in_array($item->lampiran_path, $lampiranPaths)) {
                            $lampiranPaths[] = $item->lampiran_path;
                        }
                        $imagePaths = collect($lampiranPaths)->filter(fn($path) => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path));
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $invoiceNomor }}</td>
                        <td>{{ $item->tgl_transaksi ? $item->tgl_transaksi->format('d/m/Y') : '-' }}</td>
                        <td>{{ $item->tgl_jatuh_tempo ? $item->tgl_jatuh_tempo->format('d/m/Y') : '-' }}</td>
                        <td>{{ $item->syarat_pembayaran ?? '-' }}</td>
                        <td>{{ $item->pelanggan ?? '-' }}</td>
                        <td>{{ optional($item->gudang)->nama_gudang ?? '-' }}</td>
                        <td class="text-right">{{ format_rupiah($item->grand_total) }}</td>
                        <td class="text-right">{{ format_rupiah($item->total_bayar_approved) }}</td>
                        <td class="text-right">{{ format_rupiah($item->jumlah_tagihan) }}</td>
                        <td>{{ $item->koordinat ?? '-' }}</td>
                        <td>
                            @if($imagePaths->count() > 0)
                                @foreach($imagePaths as $lampiran)
                                    @php $fullPath = public_path('storage/' . $lampiran); @endphp
                                    @if(file_exists($fullPath))
                                        <img src="{{ $fullPath }}" class="lampiran-thumb" alt="Lampiran">
                                    @endif
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="9" class="text-right">Total Sisa Jatuh Tempo</td>
                    <td class="text-right">{{ format_rupiah($totalJatuhTempo) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
