@php
    $formatDate = function ($value, string $format = 'd/m/Y') {
        if (blank($value)) return '-';
        try { return \Carbon\Carbon::parse($value)->format($format); } catch (\Throwable $e) { return '-'; }
    };
@endphp
<table>
    <thead><tr><th>No</th><th>Nomor</th><th>Tanggal</th><th>Tujuan</th><th>Sales</th><th style="mso-number-format:'\@'">No Telepon Sales</th><th>Kontak</th><th style="mso-number-format:'\@'">No Telepon Kontak</th><th>Gudang</th><th>Approver</th><th>Memo</th><th>Produk</th><th>Kode Item</th><th>Jumlah</th><th>Batch</th><th>Expired</th><th>Tipe Stok</th><th>Keterangan Item</th><th>Status</th><th>Dibuat</th></tr></thead>
    <tbody>
        @foreach($transactions as $transactionIndex => $p)
            @php $items = method_exists($p, 'relationLoaded') && $p->relationLoaded('items') ? $p->items : collect([null]); $items = $items->count() ? $items : collect([null]); @endphp
            @foreach($items as $itemIndex => $item)
                @php $produk = optional($item)->produk; $expiredDate = optional($item)->expired_date; @endphp
                <tr><td>{{ $itemIndex === 0 ? $transactionIndex + 1 : '' }}</td><td>{{ $p->nomor ?? $p->custom_number ?? $p->number ?? '-' }}</td><td>{{ $formatDate($p->tgl_kunjungan) }}</td><td>{{ $p->tujuan ?? '-' }}</td><td>{{ $p->sales_nama ?? optional($p->user)->name ?? '-' }}</td><td style="mso-number-format:'\@'">{{ receipt_format_phone($p->sales_no_telepon ?? '') }}</td><td>{{ optional($p->kontak)->nama ?? $p->display_contact_name ?? '-' }}</td><td style="mso-number-format:'\@'">{{ receipt_format_phone(optional($p->kontak)->no_telp ?? $p->no_telp_kontak ?? '') }}</td><td>{{ optional($p->gudang)->nama_gudang ?? '-' }}</td><td>{{ optional($p->approver)->name ?? '-' }}</td><td>{{ $p->memo ?? '-' }}</td><td>{{ optional($produk)->nama_produk ?? '-' }}</td><td>{{ optional($produk)->item_code ?? '-' }}</td><td>{{ optional($item)->jumlah ?? '-' }}</td><td>{{ optional($item)->batch_number ?? '-' }}</td><td>{{ $formatDate($expiredDate) }}</td><td>{{ optional($item)->tipe_stok ?? '-' }}</td><td>{{ optional($item)->keterangan ?? '-' }}</td><td>{{ $p->status ?? '-' }}</td><td>{{ $itemIndex === 0 ? $formatDate($p->created_at, 'd/m/Y H:i') : '' }}</td></tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot><tr><td colspan="20" style="text-align:right; font-size:11px; color:gray;">Total Kunjungan: {{ $transactions->count() }} | Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td></tr></tfoot>
</table>
