@php
    $formatDate = function ($value, string $format = 'd/m/Y') {
        if (blank($value)) return '-';
        try { return \Carbon\Carbon::parse($value)->format($format); } catch (\Throwable $e) { return '-'; }
    };
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
    <tfoot><tr><td colspan="17"><strong>TOTAL GRAND TOTAL</strong></td><td><strong>{{ format_rupiah($transactions->sum('grand_total')) }}</strong></td><td colspan="2"></td></tr><tr><td colspan="20" style="text-align:right; font-size:11px; color:gray;">Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td></tr></tfoot>
</table>
