@php
    $validStockRows = collect($stokData ?? [])->filter(fn ($item) => is_object($item) && filled(data_get($item, 'produk')))->values();
    $salesStock = $validStockRows->sum(fn ($item) => (float) ($item->stok_penjualan ?? 0));
    $freeStock = $validStockRows->sum(fn ($item) => (float) ($item->stok_gratis ?? 0));
    $sampleStock = $validStockRows->sum(fn ($item) => (float) ($item->stok_sample ?? 0));
    $totalStock = $salesStock + $freeStock + $sampleStock;
    $stockCategories = [
        'Stok Habis' => $validStockRows->filter(fn ($item) => (float) ($item->stok_penjualan ?? 0) + (float) ($item->stok_gratis ?? 0) + (float) ($item->stok_sample ?? 0) <= 0)->count(),
        'Stok Tersedia' => $validStockRows->filter(fn ($item) => (float) ($item->stok_penjualan ?? 0) + (float) ($item->stok_gratis ?? 0) + (float) ($item->stok_sample ?? 0) > 0)->count(),
    ];
@endphp
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
        @foreach($validStockRows as $item)
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
<table>
    <thead><tr><th colspan="4">RINGKASAN STOK</th></tr></thead>
    <tbody>
        <tr><td>Total Produk Diekspor</td><td>{{ $validStockRows->count() }}</td><td>Total Seluruh Stok</td><td>{{ $totalStock }}</td></tr>
        <tr><td>Total Stok Penjualan</td><td>{{ $salesStock }}</td><td>Total Stok Gratis</td><td>{{ $freeStock }}</td></tr>
        <tr><td>Total Stok Sample</td><td>{{ $sampleStock }}</td><td></td><td></td></tr>
    </tbody>
</table>
<table>
    <thead><tr><th>Kategori Stok</th><th>Jumlah Produk</th></tr></thead>
    <tbody>
        @foreach($stockCategories as $category => $count)
            <tr><td>{{ $category }}</td><td>{{ $count }}</td></tr>
        @endforeach
    </tbody>
</table>
