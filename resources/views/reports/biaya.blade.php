@php
    $formatDate = function ($value, string $format = 'd/m/Y') {
        if (blank($value)) return '-';
        try { return \Carbon\Carbon::parse($value)->format($format); } catch (\Throwable $e) { return '-'; }
    };
    $transactionCount = $transactions->count();
    $grandTotal = $transactions->sum(fn ($transaction) => (float) ($transaction->grand_total ?? 0));
    $averageGrandTotal = $transactionCount > 0 ? $grandTotal / $transactionCount : 0;
    $statusSummary = $transactions
        ->groupBy(fn ($transaction) => filled($transaction->status) ? (string) $transaction->status : 'Tanpa Status')
        ->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum(fn ($transaction) => (float) ($transaction->grand_total ?? 0)),
        ]);
    $jenisSummary = $transactions
        ->groupBy(fn ($transaction) => filled($transaction->jenis_biaya) ? (string) $transaction->jenis_biaya : 'Tanpa Jenis')
        ->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum(fn ($transaction) => (float) ($transaction->grand_total ?? 0)),
        ]);
    $totalMasuk = $transactions
        ->filter(fn ($transaction) => str_contains(strtolower((string) ($transaction->jenis_biaya ?? '')), 'masuk'))
        ->sum(fn ($transaction) => (float) ($transaction->grand_total ?? 0));
    $totalKeluar = $transactions
        ->filter(fn ($transaction) => str_contains(strtolower((string) ($transaction->jenis_biaya ?? '')), 'keluar'))
        ->sum(fn ($transaction) => (float) ($transaction->grand_total ?? 0));
    $netBiaya = $totalMasuk - $totalKeluar;
@endphp
<table>
    <thead><tr><th>No</th><th>Nomor</th><th>Tanggal</th><th>Jenis Biaya</th><th>Bayar Dari</th><th>Penerima</th><th style="mso-number-format:'\@'">No Telepon</th><th>Cara Pembayaran</th><th>Pembuat</th><th>Approver</th><th>Gudang</th><th>Tag</th><th>Memo</th><th>Kategori Item</th><th>Deskripsi Item</th><th>Jumlah Item</th><th>Pajak %</th><th>Grand Total</th><th>Status</th><th>Dibuat</th></tr></thead>
    <tbody>
        @foreach($transactions as $transactionIndex => $p)
            @php $items = method_exists($p, 'relationLoaded') && $p->relationLoaded('items') ? $p->items : collect([null]); $items = $items->count() ? $items : collect([null]); @endphp
            @foreach($items as $itemIndex => $item)
                <tr><td>{{ $itemIndex === 0 ? $transactionIndex + 1 : '' }}</td><td>{{ $p->nomor ?? $p->custom_number ?? $p->number ?? '-' }}</td><td>{{ $formatDate($p->tgl_transaksi) }}</td><td>{{ $p->jenis_biaya ?? '-' }}</td><td>{{ $p->bayar_dari ?? '-' }}</td><td>{{ $p->penerima ?? $p->display_contact_name ?? '-' }}</td><td style="mso-number-format:'\@'">{{ receipt_format_phone($p->no_telepon ?? $p->no_telp_kontak ?? '') }}</td><td>{{ $p->cara_pembayaran ?? '-' }}</td><td>{{ optional($p->user)->name ?? '-' }}</td><td>{{ optional($p->approver)->name ?? '-' }}</td><td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td><td>{{ $p->tag ?? '-' }}</td><td>{{ $p->memo ?? '-' }}</td><td>{{ optional($item)->kategori ?? '-' }}</td><td>{{ optional($item)->deskripsi ?? '-' }}</td><td>{{ optional($item)->jumlah !== null ? format_rupiah(optional($item)->jumlah) : '-' }}</td><td>{{ $itemIndex === 0 ? ($p->tax_percentage ?? 0) : '' }}</td><td>{{ $itemIndex === 0 ? format_rupiah($p->grand_total ?? 0) : '' }}</td><td>{{ $p->status ?? '-' }}</td><td>{{ $itemIndex === 0 ? $formatDate($p->created_at, 'd/m/Y H:i') : '' }}</td></tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot><tr><td colspan="17"><strong>TOTAL GRAND TOTAL</strong></td><td><strong>{{ format_rupiah($grandTotal) }}</strong></td><td colspan="2"></td></tr><tr><td colspan="20" style="text-align:right; font-size:11px; color:gray;">Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td></tr></tfoot>
</table>
<table>
    <tbody>
        <tr><td colspan="20"><strong>RINGKASAN UTAMA</strong></td></tr>
        <tr><td>Jumlah Transaksi</td><td>{{ $transactionCount }}</td><td>Total Grand Total</td><td>{{ format_rupiah($grandTotal) }}</td><td>Rata-rata Grand Total</td><td>{{ format_rupiah($averageGrandTotal) }}</td><td colspan="14"></td></tr>
        <tr><td>Total Masuk</td><td>{{ format_rupiah($totalMasuk) }}</td><td>Total Keluar</td><td>{{ format_rupiah($totalKeluar) }}</td><td>Net</td><td>{{ format_rupiah($netBiaya) }}</td><td colspan="14"></td></tr>
        <tr><td colspan="20"><strong>RINGKASAN STATUS</strong></td></tr>
        <tr><th>Status</th><th>Jumlah Transaksi</th><th>Total Grand Total</th><th colspan="17"></th></tr>
        @forelse($statusSummary as $status => $summary)
            <tr><td>{{ $status }}</td><td>{{ $summary['count'] }}</td><td>{{ format_rupiah($summary['total']) }}</td><td colspan="17"></td></tr>
        @empty
            <tr><td>Tanpa Data</td><td>0</td><td>{{ format_rupiah(0) }}</td><td colspan="17"></td></tr>
        @endforelse
        <tr><td colspan="20"><strong>RINGKASAN JENIS BIAYA</strong></td></tr>
        <tr><th>Jenis Biaya</th><th>Jumlah Transaksi</th><th>Total Grand Total</th><th colspan="17"></th></tr>
        @forelse($jenisSummary as $jenis => $summary)
            <tr><td>{{ $jenis }}</td><td>{{ $summary['count'] }}</td><td>{{ format_rupiah($summary['total']) }}</td><td colspan="17"></td></tr>
        @empty
            <tr><td>Tanpa Data</td><td>0</td><td>{{ format_rupiah(0) }}</td><td colspan="17"></td></tr>
        @endforelse
    </tbody>
</table>
