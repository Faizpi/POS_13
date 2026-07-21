<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { font-size: 10px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #2d6a4f; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .section-title { background: #e2efda; font-weight: bold; color: #2d6a4f; }
        .total-row { font-weight: bold; background: #d1fae5; }
        .danger-row { font-weight: bold; background: #fee2e2; color: #dc2626; }
        .text-right { text-align: right; }
        .summary-box { display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 16px; margin: 4px; min-width: 180px; }
        .summary-label { font-size: 9px; color: #6b7280; }
        .summary-value { font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Neraca Keuangan</h1>
    <div class="meta">
        Gudang: <strong>{{ $gudang }}</strong> &nbsp;|&nbsp;
        Periode: <strong>{{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Semua' }}</strong>
        s/d <strong>{{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Semua' }}</strong><br>
        Dicetak oleh: {{ $generatedBy ?? auth()->user()?->name ?? 'System' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
    </div>

    {{-- OMSET --}}
    <table>
        <thead>
            <tr><th colspan="2">Omset Pergudang</th></tr>
            <tr><th>Gudang</th><th class="text-right">Nilai (Rp)</th></tr>
        </thead>
        <tbody>
            @forelse($data['omset'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="text-right" style="color:#9ca3af;">Tidak ada data</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="text-right">{{ number_format($data['total_omset'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- PEMBELIAN --}}
    <table>
        <thead>
            <tr><th colspan="2">Nilai Pembelian Gudang</th></tr>
            <tr><th>Gudang</th><th class="text-right">Nilai (Rp)</th></tr>
        </thead>
        <tbody>
            @forelse($data['pembelian'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" style="color:#9ca3af;">Tidak ada data</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="text-right">{{ number_format($data['total_pembelian'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- RETAIL & GROSIR --}}
    <table>
        <thead>
            <tr><th colspan="3">Penjualan per Tipe Harga</th></tr>
            <tr><th>Tipe</th><th class="text-right">Nilai (Rp)</th><th class="text-right">Qty Terjual</th></tr>
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
                <td>TOTAL</td>
                <td class="text-right">{{ number_format($data['total_retail'] + $data['total_grosir'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['qty_retail'] + $data['qty_grosir'], 0, ',', '.') }} unit</td>
            </tr>
        </tbody>
    </table>

    {{-- BELUM LUNAS --}}
    <table>
        <thead>
            <tr><th colspan="2">Pembayaran Belum Lunas Pergudang</th></tr>
            <tr><th>Gudang</th><th class="text-right">Nilai (Rp)</th></tr>
        </thead>
        <tbody>
            @forelse($data['belum_lunas'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" style="color:#9ca3af;">Tidak ada piutang belum lunas</td></tr>
            @endforelse
            <tr class="danger-row">
                <td>TOTAL BELUM LUNAS</td>
                <td class="text-right">{{ number_format($data['total_belum_lunas'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- NILAI PERSEDIAAN --}}
    <table>
        <thead>
            <tr class="section-title"><th colspan="2">NILAI PERSEDIAAN</th></tr>
            <tr><th>Gudang</th><th class="text-right">Nilai (Rp)</th></tr>
        </thead>
        <tbody>
            @forelse($data['persediaan_retail']['gudang'] as $item)
            <tr>
                <td>{{ $item['gudang'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="2" style="color:#9ca3af;">Tidak ada persediaan</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL NILAI PERSEDIAAN</td>
                <td class="text-right">{{ number_format($data['persediaan_retail']['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
