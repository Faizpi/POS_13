@php
    $formatDate = function ($value, string $format = 'd/m/Y') {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $resolveTransaction = static function ($transaction): array {
        $kind = class_basename($transaction);
        $value = match ($kind) {
            'Penjualan', 'Pembelian', 'Biaya' => (float) ($transaction->grand_total ?? 0),
            'Pembayaran' => (float) ($transaction->jumlah_bayar ?? 0),
            'Kunjungan' => null,
            default => null,
        };

        return ['kind' => $kind, 'value' => $value];
    };

    $transactionCollection = collect($transactions ?? []);
    $resolvedTransactions = $transactionCollection->map(function ($transaction) use ($resolveTransaction): array {
        $resolved = $resolveTransaction($transaction);

        return [
            'kind' => $resolved['kind'],
            'status' => filled($transaction->status ?? null) ? (string) $transaction->status : 'Tanpa Status',
            'value' => $resolved['value'],
        ];
    });
    $mainTotal = $resolvedTransactions->sum(fn (array $transaction): float => $transaction['value'] ?? 0);
    $typeSummary = $resolvedTransactions->groupBy('kind')->map(fn ($rows): array => [
        'count' => $rows->count(),
        'value' => $rows->sum(fn (array $row): float => $row['value'] ?? 0),
        'has_value' => $rows->contains(fn (array $row): bool => $row['value'] !== null),
    ]);
    $statusSummary = $resolvedTransactions->groupBy('status')->map(fn ($rows): array => [
        'count' => $rows->count(),
        'value' => $rows->sum(fn (array $row): float => $row['value'] ?? 0),
        'has_value' => $rows->contains(fn (array $row): bool => $row['value'] !== null),
    ]);
@endphp

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Tipe</th>
            <th>Nomor</th>
            <th>Tanggal</th>
            <th>Pembuat/Sales</th>
            <th>Approver</th>
            <th>Gudang</th>
            <th>Kontak/Penerima</th>
            <th style="mso-number-format:'\@'">No Telepon</th>
            <th>Status</th>
            <th>Referensi</th>
            <th>Memo/Keterangan</th>
            <th>Produk/Kategori</th>
            <th>Kode Item</th>
            <th>Deskripsi Item</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Harga/Jumlah</th>
            <th>Diskon</th>
            <th>Subtotal Item</th>
            <th>Batch</th>
            <th>Expired</th>
            <th>Tipe Stok</th>
            <th>Diskon Akhir</th>
            <th>Pajak %</th>
            <th>Grand Total/Jumlah Bayar</th>
            <th>Dibuat</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transactions as $transactionIndex => $t)
            @php
                $items = method_exists($t, 'relationLoaded') && $t->relationLoaded('items') ? $t->items : collect([null]);
                $items = $items->count() ? $items : collect([null]);
                $transactionNumber = $t->nomor ?? $t->custom_number ?? $t->number ?? '-';
                $transactionDate = $t->tgl_transaksi ?? $t->tgl_kunjungan ?? $t->tgl_pembayaran ?? $t->tgl_penerimaan ?? null;
                $contactName = $t->display_contact_name ?? $t->pelanggan ?? $t->penerima ?? optional($t->kontak)->nama ?? optional($t->penjualan)->pelanggan ?? '-';
                $phone = $t->no_telepon ?? $t->no_telp_kontak ?? $t->sales_no_telepon ?? '-';
                $resolvedTransaction = $resolveTransaction($t);
                $transactionKind = $resolvedTransaction['kind'];
                $transactionValue = $resolvedTransaction['value'];
                $reference = $t->no_referensi ?? optional($t->penjualan)->custom_number ?? optional($t->penjualan)->nomor ?? '-';
            @endphp
            @foreach($items as $itemIndex => $item)
                @php
                    $produk = optional($item)->produk;
                    $qty = $transactionKind === 'Kunjungan'
                        ? (optional($item)->jumlah ?? optional($item)->kuantitas ?? optional($item)->qty ?? optional($item)->qty_diterima ?? null)
                        : (optional($item)->kuantitas ?? optional($item)->jumlah ?? optional($item)->qty_diterima ?? null);
                    $price = optional($item)->harga_satuan ?? optional($item)->jumlah ?? null;
                    $lineTotal = optional($item)->jumlah_baris ?? optional($item)->jumlah ?? (($qty !== null && $price !== null) ? ((float) $qty * (float) $price) : null);
                    $expiredDate = optional($item)->expired_date;
                @endphp
                <tr>
                    <td>{{ $itemIndex === 0 ? $transactionIndex + 1 : '' }}</td>
                    <td>{{ $transactionKind }}</td>
                    <td>{{ $transactionNumber }}</td>
                    <td>{{ $formatDate($transactionDate) }}</td>
                    <td>{{ optional($t->user)->name ?? $t->sales_nama ?? '-' }}</td>
                    <td>{{ optional($t->approver)->name ?? $t->staf_penyetuju ?? '-' }}</td>
                    <td>{{ optional($t->gudang)->nama_gudang ?? '-' }}</td>
                    <td>{{ $contactName }}</td>
                    <td style="mso-number-format:'\@'">{{ receipt_format_phone($t->no_telepon ?? $t->no_telp_kontak ?? $t->sales_no_telepon ?? '') }}</td>
                    <td>{{ $t->status ?? '-' }}</td>
                    <td>{{ $reference }}</td>
                    <td>{{ $t->memo ?? $t->keterangan ?? '-' }}</td>
                    <td>{{ optional($produk)->nama_produk ?? optional($item)->kategori ?? '-' }}</td>
                    <td>{{ optional($produk)->item_code ?? '-' }}</td>
                    <td>{{ optional($item)->deskripsi ?? optional($item)->keterangan ?? optional($produk)->deskripsi ?? '-' }}</td>
                    <td>{{ $qty ?? '-' }}</td>
                    <td>{{ optional($item)->unit ?? optional($produk)->satuan ?? '-' }}</td>
                    <td>{{ $transactionKind === 'Kunjungan' ? '-' : ($price !== null ? format_rupiah($price) : '-') }}</td>
                    <td>{{ in_array($transactionKind, ['Pembayaran', 'Kunjungan'], true) ? '-' : (optional($item)->diskon_nominal !== null ? format_rupiah(optional($item)->diskon_nominal) : (optional($item)->diskon ?? '-')) }}</td>
                    <td>{{ $transactionKind === 'Kunjungan' ? '-' : ($lineTotal !== null ? format_rupiah($lineTotal) : '-') }}</td>
                    <td>{{ optional($item)->batch_number ?? '-' }}</td>
                    <td>{{ $formatDate($expiredDate) }}</td>
                    <td>{{ optional($item)->tipe_stok ?? '-' }}</td>
                    <td>{{ $itemIndex === 0 ? (in_array($transactionKind, ['Pembayaran', 'Kunjungan'], true) ? '-' : format_rupiah($t->diskon_akhir ?? 0)) : '' }}</td>
                    <td>{{ $itemIndex === 0 ? (in_array($transactionKind, ['Pembayaran', 'Kunjungan'], true) ? '-' : ($t->tax_percentage ?? 0)) : '' }}</td>
                    <td>{{ $itemIndex === 0 ? ($transactionValue === null ? '-' : format_rupiah($transactionValue)) : '' }}</td>
                    <td>{{ $itemIndex === 0 ? $formatDate($t->created_at, 'd/m/Y H:i') : '' }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="27"><strong>RINGKASAN UTAMA</strong></td>
        </tr>
        <tr>
            <td colspan="25"><strong>Total Transaksi</strong></td>
            <td><strong>{{ $resolvedTransactions->count() }}</strong></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="25"><strong>Total Nilai</strong></td>
            <td><strong>{{ format_rupiah($mainTotal) }}</strong></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="27"><strong>RINGKASAN PER TIPE</strong></td>
        </tr>
        @forelse($typeSummary as $kind => $summary)
            <tr>
                <td colspan="23">{{ $kind }}</td>
                <td colspan="2">{{ $summary['count'] }} transaksi</td>
                <td>{{ $summary['has_value'] ? format_rupiah($summary['value']) : '-' }}</td>
                <td></td>
            </tr>
        @empty
            <tr>
                <td colspan="27">Tidak ada transaksi.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="27"><strong>RINGKASAN PER STATUS</strong></td>
        </tr>
        @forelse($statusSummary as $status => $summary)
            <tr>
                <td colspan="23">{{ $status }}</td>
                <td colspan="2">{{ $summary['count'] }} transaksi</td>
                <td>{{ $summary['has_value'] ? format_rupiah($summary['value']) : '-' }}</td>
                <td></td>
            </tr>
        @empty
            <tr>
                <td colspan="27">Tidak ada status transaksi.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="27" style="text-align:right; font-size:11px; color:gray;">
                Dibuat oleh: {{ $generatedBy ?? 'System' }} | {{ $generatedAt ?? now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </tfoot>
</table>
