<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Item Code</th>
            <th>Nama Produk</th>
            <th>Satuan</th>
            <th>Stok Penjualan</th>
            <th>Stok Gratis</th>
            <th>Stok Sample</th>
            <th>Total Stok</th>
        </tr>
    </thead>
    <tbody>
        @php $no = 1; @endphp
        @foreach(($stokData ?? collect()) as $item)
        @if($item->produk)
        <tr>
            <td>{{ $no++ }}</td>
            <td>{{ $item->produk->item_code ?? '-' }}</td>
            <td>{{ $item->produk->nama_produk }}</td>
            <td>{{ $item->produk->satuan ?? 'Pcs' }}</td>
            <td>{{ $item->stok_penjualan ?? 0 }}</td>
            <td>{{ $item->stok_gratis ?? 0 }}</td>
            <td>{{ $item->stok_sample ?? 0 }}</td>
            <td><strong>{{ ($item->stok_penjualan ?? 0) + ($item->stok_gratis ?? 0) + ($item->stok_sample ?? 0) }}</strong></td>
        </tr>
        @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8" style="text-align:right; font-size:10px; color:gray;">
                Gudang: {{ optional($gudang ?? null)->nama_gudang ?? '-' }} |
                Dicetak oleh: {{ $generatedBy ?? 'System' }} |
                {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </tfoot>
</table>
