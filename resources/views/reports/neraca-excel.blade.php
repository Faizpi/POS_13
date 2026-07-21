<table>
    <thead>
        <tr>
            <th colspan="3" style="font-size: 14pt; font-weight: bold;">Neraca Keuangan — {{ $gudang }}</th>
        </tr>
        <tr>
            <th colspan="3">Periode: {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Semua' }} s/d {{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Semua' }}</th>
        </tr>
        <tr>
            <th colspan="3">Dibuat oleh: {{ $generatedBy }} pada {{ $generatedAt }}</th>
        </tr>
        <tr></tr>
        <tr>
            <th style="font-weight: bold; background-color: #E2EFDA;">Metrik</th>
            <th style="font-weight: bold; background-color: #E2EFDA;">Gudang</th>
            <th style="font-weight: bold; background-color: #E2EFDA;">Nilai (Rp)</th>
        </tr>
    </thead>
    <tbody>
        {{-- OMSET --}}
        @foreach($data['omset'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Omset Pergudang' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL OMSET</td>
            <td></td>
            <td style="font-weight: bold;">{{ number_format($data['total_omset'], 2, ',', '.') }}</td>
        </tr>
        <tr></tr>

        {{-- PEMBELIAN --}}
        @foreach($data['pembelian'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Nilai Pembelian Gudang' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL PEMBELIAN</td>
            <td></td>
            <td style="font-weight: bold;">{{ number_format($data['total_pembelian'], 2, ',', '.') }}</td>
        </tr>
        <tr></tr>

        {{-- RETAIL --}}
        @foreach($data['retail'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Nilai Penjualan Retail' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL RETAIL</td>
            <td></td>
            <td style="font-weight: bold;">{{ number_format($data['total_retail'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Jumlah Produk Terjual Retail</td>
            <td></td>
            <td>{{ number_format($data['qty_retail'], 0, ',', '.') }} unit</td>
        </tr>
        <tr></tr>

        {{-- GROSIR --}}
        @foreach($data['grosir'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Nilai Penjualan Grosir' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL GROSIR</td>
            <td></td>
            <td style="font-weight: bold;">{{ number_format($data['total_grosir'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Jumlah Produk Terjual Grosir</td>
            <td></td>
            <td>{{ number_format($data['qty_grosir'], 0, ',', '.') }} unit</td>
        </tr>
        <tr></tr>

        {{-- BELUM LUNAS --}}
        @foreach($data['belum_lunas'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Pembayaran Belum Lunas' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL BELUM LUNAS</td>
            <td></td>
            <td style="font-weight: bold; color: red;">{{ number_format($data['total_belum_lunas'], 2, ',', '.') }}</td>
        </tr>
        <tr></tr>

        {{-- NILAI PERSEDIAAN --}}
        @foreach($data['persediaan_retail']['gudang'] as $item)
        <tr>
            <td>{{ $loop->first ? 'Nilai Persediaan' : '' }}</td>
            <td>{{ $item['gudang'] }}</td>
            <td>{{ number_format($item['total'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold;">TOTAL NILAI PERSEDIAAN</td>
            <td></td>
            <td style="font-weight: bold;">{{ number_format($data['persediaan_retail']['total'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
