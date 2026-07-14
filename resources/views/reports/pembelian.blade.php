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
@endphp
<table>
    <thead><tr><th>No</th><th>Nomor</th><th>Tanggal</th><th>Gudang</th><th>Pembuat</th><th>Approver</th><th>Staf Penyetuju</th><th>Email Penyetuju</th><th>Urgensi</th><th>Tahun Anggaran</th><th>Syarat Pembayaran</th><th>Tgl Jatuh Tempo</th><th>Tag</th><th>Memo</th><th>Produk</th><th>Kode Item</th><th>Deskripsi</th><th>Qty</th><th>Unit</th><th>Harga Satuan</th><th>Diskon</th><th>Subtotal Item</th><th>Diskon Akhir</th><th>Pajak %</th><th>Grand Total</th><th>Status</th><th>Dibuat</th></tr></thead>
    <tbody>
        @foreach($transactions as $transactionIndex => $p)
            @php $items = method_exists($p, 'relationLoaded') && $p->relationLoaded('items') ? $p->items : collect([null]); $items = $items->count() ? $items : collect([null]); @endphp
            @foreach($items as $itemIndex => $item)
                @php $produk = optional($item)->produk; $qty = optional($item)->kuantitas; $price = optional($item)->harga_satuan; $lineTotal = optional($item)->jumlah_baris ?? (($qty !== null && $price !== null) ? ((float) $qty * (float) $price) : null); @endphp
                <tr><td>{{ $itemIndex === 0 ? $transactionIndex + 1 : '' }}</td><td>{{ $p->nomor ?? $p->custom_number ?? $p->number ?? '-' }}</td><td>{{ $formatDate($p->tgl_transaksi) }}</td><td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td><td>{{ optional($p->user)->name ?? '-' }}</td><td>{{ optional($p->approver)->name ?? '-' }}</td><td>{{ $p->staf_penyetuju ?? '-' }}</td><td>{{ $p->email_penyetuju ?? '-' }}</td><td>{{ $p->urgensi ?? '-' }}</td><td>{{ $p->tahun_anggaran ?? '-' }}</td><td>{{ $p->syarat_pembayaran ?? '-' }}</td><td>{{ $formatDate($p->tgl_jatuh_tempo) }}</td><td>{{ $p->tag ?? '-' }}</td><td>{{ $p->memo ?? '-' }}</td><td>{{ optional($produk)->nama_produk ?? '-' }}</td><td>{{ optional($produk)->item_code ?? '-' }}</td><td>{{ optional($item)->deskripsi ?? optional($produk)->deskripsi ?? '-' }}</td><td>{{ $qty ?? '-' }}</td><td>{{ optional($item)->unit ?? optional($produk)->satuan ?? '-' }}</td><td>{{ $price !== null ? format_rupiah($price) : '-' }}</td><td>{{ optional($item)->diskon ?? '-' }}</td><td>{{ $lineTotal !== null ? format_rupiah($lineTotal) : '-' }}</td><td>{{ $itemIndex === 0 ? format_rupiah($p->diskon_akhir ?? 0) : '' }}</td><td>{{ $itemIndex === 0 ? ($p->tax_percentage ?? 0) : '' }}</td><td>{{ $itemIndex === 0 ? format_rupiah($p->grand_total ?? 0) : '' }}</td><td>{{ $p->status ?? '-' }}</td><td>{{ $itemIndex === 0 ? $formatDate($p->created_at, 'd/m/Y H:i') : '' }}</td></tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot><tr><td colspan="24"><strong>TOTAL GRAND TOTAL</strong></td><td><strong>{{ format_rupiah($grandTotal) }}</strong></td><td colspan="2"></td></tr><tr><td colspan="27" style="text-align:right; font-size:11px; color:gray;">Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td></tr></tfoot>
</table>
<table>
    <tbody>
        <tr><td colspan="27"><strong>RINGKASAN UTAMA</strong></td></tr>
        <tr><td>Jumlah Transaksi</td><td>{{ $transactionCount }}</td><td>Total Grand Total</td><td>{{ format_rupiah($grandTotal) }}</td><td>Rata-rata Grand Total</td><td>{{ format_rupiah($averageGrandTotal) }}</td><td colspan="21"></td></tr>
        <tr><td colspan="27"><strong>RINGKASAN STATUS</strong></td></tr>
        <tr><th>Status</th><th>Jumlah Transaksi</th><th>Total Grand Total</th><th colspan="24"></th></tr>
        @forelse($statusSummary as $status => $summary)
            <tr><td>{{ $status }}</td><td>{{ $summary['count'] }}</td><td>{{ format_rupiah($summary['total']) }}</td><td colspan="24"></td></tr>
        @empty
            <tr><td>Tanpa Data</td><td>0</td><td>{{ format_rupiah(0) }}</td><td colspan="24"></td></tr>
        @endforelse
    </tbody>
</table>
