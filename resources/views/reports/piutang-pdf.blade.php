<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
h1 { font-size: 16px; margin-bottom: 2px; }
.meta { font-size: 10px; color: #666; margin-bottom: 16px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
tr:nth-child(even) { background: #f9fafb; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fef3c7; color: #d97706; }
.danger { color: #dc2626; font-weight: bold; }
.total-row { font-weight: bold; background: #e2efda; }
</style>
</head>
<body>
<h1>Laporan Piutang</h1>
<div class="meta">
Periode: {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Semua' }} s/d {{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Semua' }}<br>
Dicetak oleh: {{ $generatedBy }} | {{ now()->format('d/m/Y H:i') }}
</div>

<table>
<thead>
<tr><th>Pelanggan</th><th>Nomor</th><th>Gudang</th><th>Jatuh Tempo</th><th class="text-right">Total</th><th class="text-right">Sudah Bayar</th><th class="text-right">Sisa</th><th class="text-center">Status</th></tr>
</thead>
<tbody>
@forelse($list as $item)
<tr>
<td>{{ $item['pelanggan'] ?? '—' }}</td>
<td>{{ $item['nomor'] }}</td>
<td>{{ $item['gudang'] ?? '—' }}</td>
<td class="{{ $item['jatuh_tempo_lewat'] ? 'danger' : '' }}">{{ $item['tgl_jatuh_tempo'] ?? '—' }}</td>
<td class="text-right">{{ number_format($item['grand_total'], 0, ',', '.') }}</td>
<td class="text-right">{{ number_format($item['sudah_bayar'], 0, ',', '.') }}</td>
<td class="text-right {{ $item['sisa'] > 0 ? 'danger' : '' }}">{{ number_format($item['sisa'], 0, ',', '.') }}</td>
<td class="text-center"><span class="badge {{ $item['status'] === 'Lunas' ? 'badge-success' : 'badge-warning' }}">{{ $item['status'] }}</span></td>
</tr>
@empty
<tr><td colspan="8" style="text-align:center;color:#9ca3af;">Tidak ada data</td></tr>
@endforelse
</tbody>
<tfoot>
<tr class="total-row"><td colspan="4">TOTAL</td><td class="text-right">{{ number_format($list->sum('grand_total'), 0, ',', '.') }}</td><td class="text-right">{{ number_format($list->sum('sudah_bayar'), 0, ',', '.') }}</td><td class="text-right danger">{{ number_format($list->sum('sisa'), 0, ',', '.') }}</td><td></td></tr>
</tfoot>
</table>
</body>
</html>
