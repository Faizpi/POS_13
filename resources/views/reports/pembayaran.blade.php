@php
    $formatDate = function ($value, string $format = 'd/m/Y') {
        if (blank($value)) return '-';
        try { return \Carbon\Carbon::parse($value)->format($format); } catch (\Throwable $e) { return '-'; }
    };
@endphp
<table>
    <thead><tr><th>No</th><th>Nomor Pembayaran</th><th>Tanggal</th><th>Invoice Penjualan</th><th>Tanggal Invoice</th><th>Pelanggan</th><th style="mso-number-format:'\@'">No Telepon</th><th>Sales/Pembuat</th><th>Approver</th><th>Gudang</th><th>Metode</th><th>Jumlah Bayar</th><th>Grand Total Invoice</th><th>Status Invoice</th><th>Bukti Bayar</th><th>Keterangan</th><th>Status Pembayaran</th><th>Dibuat</th></tr></thead>
    <tbody>
        @foreach($transactions as $i => $p)
            @php $penjualan = $p->penjualan; @endphp
            <tr><td>{{ $i + 1 }}</td><td>{{ $p->nomor ?? $p->custom_number ?? $p->number ?? '-' }}</td><td>{{ $formatDate($p->tgl_pembayaran) }}</td><td>{{ optional($penjualan)->nomor ?? optional($penjualan)->custom_number ?? '-' }}</td><td>{{ $formatDate(optional($penjualan)->tgl_transaksi) }}</td><td>{{ optional($penjualan)->pelanggan ?? $p->display_contact_name ?? '-' }}</td><td style="mso-number-format:'\@'">{{ receipt_format_phone(optional($penjualan)->no_telepon ?? $p->no_telp_kontak ?? '') }}</td><td>{{ optional($p->user)->name ?? '-' }}</td><td>{{ optional($p->approver)->name ?? '-' }}</td><td>{{ optional($p->gudang)->nama_gudang ?? optional(optional($penjualan)->gudang)->nama_gudang ?? '-' }}</td><td>{{ $p->metode_pembayaran ?? '-' }}</td><td>{{ format_rupiah($p->jumlah_bayar ?? 0) }}</td><td>{{ optional($penjualan)->grand_total !== null ? format_rupiah(optional($penjualan)->grand_total) : '-' }}</td><td>{{ optional($penjualan)->status ?? '-' }}</td><td>{{ $p->bukti_bayar ? basename($p->bukti_bayar) : '-' }}</td><td>{{ $p->keterangan ?? '-' }}</td><td>{{ $p->status ?? '-' }}</td><td>{{ $formatDate($p->created_at, 'd/m/Y H:i') }}</td></tr>
        @endforeach
    </tbody>
    <tfoot><tr><td colspan="11"><strong>TOTAL JUMLAH BAYAR</strong></td><td><strong>{{ format_rupiah($transactions->sum('jumlah_bayar')) }}</strong></td><td colspan="6"></td></tr><tr><td colspan="18" style="text-align:right; font-size:11px; color:gray;">Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td></tr></tfoot>
</table>
