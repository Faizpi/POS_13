<table>
    <thead>
        {{-- TITLE BLOCK --}}
        <tr>
            <th colspan="2" style="font-size: 14pt; font-weight: bold; text-align: left;">Ringkasan Bisnis</th>
        </tr>
        <tr>
            <th colspan="2" style="font-size: 10pt; text-align: left;">Gudang: {{ $gudang }}</th>
        </tr>
        <tr>
            <th colspan="2" style="font-size: 10pt; text-align: left;">Periode: {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Semua' }} s/d {{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Semua' }}</th>
        </tr>
        <tr>
            <th colspan="2" style="font-size: 9pt; text-align: left; color: #666;">Dicetak oleh: {{ $generatedBy }} pada {{ $generatedAt }}</th>
        </tr>
        <tr></tr>
    </thead>
    <tbody>
        {{-- SECTION A: OMSET PERGUDANG --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">A. Omset Pergudang</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['omset'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada data omset</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL OMSET</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['total_omset'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION B: NILAI PEMBELIAN GUDANG --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">B. Nilai Pembelian Gudang</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['pembelian'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada data pembelian</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL PEMBELIAN</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['total_pembelian'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION C: PENJUALAN RETAIL PER GUDANG --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">C. Nilai Penjualan Retail</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['retail'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada data penjualan retail</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL RETAIL</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['total_retail'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION D: PENJUALAN GROSIR PER GUDANG --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">D. Nilai Penjualan Grosir</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['grosir'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada data penjualan grosir</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL GROSIR</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['total_grosir'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION E: PEMBAYARAN BELUM LUNAS --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">E. Pembayaran Belum Lunas Pergudang</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['belum_lunas'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada piutang belum lunas</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold; color: #991B1B;">TOTAL BELUM LUNAS</td>
            <td style="font-weight: bold; text-align: right; color: #991B1B;">{{ $data['total_belum_lunas'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION F: NILAI PERSEDIAAN RETAIL (CURRENT STOCK) --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">F. Nilai Persediaan Retail (Stok Saat Ini)</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['persediaan_retail']['gudang'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada persediaan retail</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL PERSEDIAAN RETAIL</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['persediaan_retail']['total'] }}</td>
        </tr>
        <tr></tr>

        {{-- SECTION G: NILAI PERSEDIAAN GROSIR (CURRENT STOCK) --}}
        <tr>
            <td colspan="2" style="font-weight: bold; background-color: #E2EFDA; text-transform: uppercase;">G. Nilai Persediaan Grosir (Stok Saat Ini)</td>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: left; text-transform: uppercase; font-size: 9pt;">Gudang</th>
            <th style="font-weight: bold; background-color: #F3F4F6; text-align: right; text-transform: uppercase; font-size: 9pt;">Nilai (Rp)</th>
        </tr>
        @forelse($data['persediaan_grosir']['gudang'] as $item)
        <tr>
            <td>{{ $item['gudang'] }}</td>
            <td style="text-align: right;">{{ $item['total'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" style="text-align: center; color: #9CA3AF; font-style: italic;">Tidak ada persediaan grosir</td>
        </tr>
        @endforelse
        <tr>
            <td style="font-weight: bold;">TOTAL PERSEDIAAN GROSIR</td>
            <td style="font-weight: bold; text-align: right;">{{ $data['persediaan_grosir']['total'] }}</td>
        </tr>
    </tbody>
</table>
