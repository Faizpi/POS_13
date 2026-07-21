<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        /* Base Typography & Colors */
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }

        /* Header Block */
        .report-header {
            border-bottom: 2px solid #2d6a4f;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }
        h1 {
            font-size: 16px;
            font-weight: bold;
            color: #2d6a4f;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta {
            font-size: 9px;
            color: #4b5563;
        }
        .meta strong { color: #1f2937; }

        /* Table Structure */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        /* Section Title (Green Band) */
        .section-title {
            background: #e2efda;
            font-weight: bold;
            color: #2d6a4f;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .section-title td {
            padding: 4px 6px;
            border: 1px solid #c6e0b4;
        }

        /* Column Headers */
        th {
            background: #f3f4f6;
            color: #374151;
            padding: 4px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #d1d5db;
        }

        /* Data Cells */
        td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        /* Alignment */
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Total Row */
        .total-row {
            font-weight: bold;
            background: #f9fafb;
            border-top: 1px solid #9ca3af;
        }
        .total-row td {
            border-bottom: 1px solid #9ca3af;
        }

        /* Danger/Alert Row (Belum Lunas) */
        .danger-row {
            font-weight: bold;
            background: #fee2e2;
            color: #991b1b;
        }
        .danger-row td {
            border-bottom: 1px solid #fca5a5;
        }

        /* Empty State */
        .empty-state {
            color: #9ca3af;
            font-style: italic;
            text-align: center;
            padding: 8px;
        }

        /* Footer / Note */
        .footer-note {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    {{-- HEADER --}}
    <div class="report-header">
        <h1>Neraca Keuangan</h1>
        <div class="meta">
            <table style="width: 100%; border: none; margin: 0;">
                <tr style="background: none;">
                    <td style="border: none; padding: 0; width: 50%;">
                        Gudang: <strong>{{ $gudang }}</strong>
                    </td>
                    <td style="border: none; padding: 0; width: 50%; text-align: right;">
                        Dicetak: {{ $generatedAt ?? now()->format('d/m/Y H:i') }}
                    </td>
                </tr>
                <tr style="background: none;">
                    <td colspan="2" style="border: none; padding: 2px 0 0 0;">
                        Periode: <strong>{{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Semua' }}</strong> s/d <strong>{{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Semua' }}</strong>
                        &nbsp;|&nbsp; Oleh: {{ $generatedBy ?? auth()->user()?->name ?? 'System' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- SECTION 1: OMSET PERGUDANG --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="2">A. Omset Pergudang</td></tr>
            <tr>
                <th style="width: 60%;">Gudang</th>
                <th class="text-right" style="width: 40%;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['omset'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty-state">Tidak ada data omset</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL OMSET</td>
                <td class="text-right">{{ number_format($data['total_omset'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- SECTION 2: NILAI PEMBELIAN --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="2">B. Nilai Pembelian Gudang</td></tr>
            <tr>
                <th style="width: 60%;">Gudang</th>
                <th class="text-right" style="width: 40%;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['pembelian'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty-state">Tidak ada data pembelian</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL PEMBELIAN</td>
                <td class="text-right">{{ number_format($data['total_pembelian'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- SECTION 3: PENJUALAN PER TIPE HARGA --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="3">C. Penjualan per Tipe Harga</td></tr>
            <tr>
                <th style="width: 40%;">Tipe</th>
                <th class="text-right" style="width: 30%;">Nilai (Rp)</th>
                <th class="text-right" style="width: 30%;">Qty Terjual</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Retail</td>
                <td class="text-right">{{ number_format($data['total_retail'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['qty_retail'], 0, ',', '.') }} unit</td>
            </tr>
            <tr>
                <td>Grosir</td>
                <td class="text-right">{{ number_format($data['total_grosir'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['qty_grosir'], 0, ',', '.') }} unit</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL PENJUALAN</td>
                <td class="text-right">{{ number_format($data['total_retail'] + $data['total_grosir'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['qty_retail'] + $data['qty_grosir'], 0, ',', '.') }} unit</td>
            </tr>
        </tbody>
    </table>

    {{-- SECTION 4: PEMBAYARAN BELUM LUNAS --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="2">D. Pembayaran Belum Lunas Pergudang</td></tr>
            <tr>
                <th style="width: 60%;">Gudang</th>
                <th class="text-right" style="width: 40%;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['belum_lunas'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty-state">Tidak ada piutang belum lunas</td></tr>
            @endforelse
            <tr class="danger-row">
                <td>TOTAL BELUM LUNAS</td>
                <td class="text-right">{{ number_format($data['total_belum_lunas'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- SECTION 5: NILAI PERSEDIAAN RETAIL (CURRENT STOCK) --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="2">E. Nilai Persediaan Retail (Stok Saat Ini)</td></tr>
            <tr>
                <th style="width: 60%;">Gudang</th>
                <th class="text-right" style="width: 40%;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['persediaan_retail']['gudang'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty-state">Tidak ada persediaan retail</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL PERSEDIAAN RETAIL</td>
                <td class="text-right">{{ number_format($data['persediaan_retail']['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- SECTION 6: NILAI PERSEDIAAN GROSIR (CURRENT STOCK) --}}
    <table>
        <thead>
            <tr class="section-title"><td colspan="2">F. Nilai Persediaan Grosir (Stok Saat Ini)</td></tr>
            <tr>
                <th style="width: 60%;">Gudang</th>
                <th class="text-right" style="width: 40%;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['persediaan_grosir']['gudang'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty-state">Tidak ada persediaan grosir</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL PERSEDIAAN GROSIR</td>
                <td class="text-right">{{ number_format($data['persediaan_grosir']['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- FOOTER NOTE --}}
    <div class="footer-note">
        Catatan: Bagian A-D merangkum aktivitas transaksi selama periode yang dipilih. Bagian E-F mencerminkan nilai persediaan berdasarkan posisi stok saat laporan dibuat.
    </div>
</body>
</html>
