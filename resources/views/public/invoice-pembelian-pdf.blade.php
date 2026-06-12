<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pembelian {{ $pembelian->staf_penyetuju }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
        }

        .receipt {
            max-width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo {
            max-width: 50mm;
            margin-bottom: 5px;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            font-size: 10px;
        }

        td {
            padding-bottom: 3px;
            vertical-align: top;
        }

        .label-col {
            width: 35%;
        }

        .colon-col {
            width: 5%;
            text-align: center;
        }

        .value-col {
            width: 60%;
        }

        .item-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .val {
            text-align: right;
        }

        .grand-total {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }

        .qr-section {
            text-align: center;
            margin-top: 12px;
        }

        .qr-section img {
            width: 25mm;
            height: 25mm;
        }

        .qr-section p {
            font-size: 8px;
            margin-top: 3px;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 9px;
        }
    </style>
</head>

<body>
    @php
        $nomorInvoice = $pembelian->nomor ?? $pembelian->custom_number ?? ('PR-' . $pembelian->id);
        $invoiceUrl = url('invoice/pembelian/' . $pembelian->uuid);

        $subtotal = $pembelian->items->sum('jumlah_baris');
        $kenaPajak = max(0, $subtotal - ($pembelian->diskon_akhir ?? 0));
        $pajakNominal = $kenaPajak * (($pembelian->tax_percentage ?? 0) / 100);
    @endphp

    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('assets/img/logoHE1.png') }}" class="logo" alt="Logo">
            <div class="title">PERMINTAAN PEMBELIAN</div>
        </div>

        <table>
            <tr>
                <td class="label-col">Nomor</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $nomorInvoice }}</td>
            </tr>
            <tr>
                <td class="label-col">Tanggal</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->tgl_transaksi->format('d/m/Y') }} |
                    {{ $pembelian->created_at->format('H:i') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Jatuh Tempo</td>
                <td class="colon-col">:</td>
                <td class="value-col">
                    {{ $pembelian->tgl_jatuh_tempo ? $pembelian->tgl_jatuh_tempo->format('d/m/Y') : '-' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Pembayaran</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->syarat_pembayaran ?? '-' }}</td>
            </tr>
            @if($pembelian->urgensi)
                <tr>
                    <td class="label-col">Urgensi</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $pembelian->urgensi }}</td>
                </tr>
            @endif
            @if($pembelian->tahun_anggaran)
                <tr>
                    <td class="label-col">Thn Anggaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $pembelian->tahun_anggaran }}</td>
                </tr>
            @endif
            <tr>
                <td class="label-col">Vendor</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->staf_penyetuju ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Pembuat</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->user->name }}</td>
            </tr>
            <tr>
                <td class="label-col">Disetujui</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->status == 'Pending' ? '-' : ($pembelian->approver->name ?? '-') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Gudang</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->gudang->nama_gudang ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Status</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembelian->status_display }}</td>
            </tr>
            @if($pembelian->memo)
                <tr>
                    <td class="label-col">Memo</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $pembelian->memo }}</td>
                </tr>
            @endif
        </table>

        <div class="divider"></div>

        @foreach($pembelian->items as $item)
            <div style="margin-bottom: 8px;">
                <div class="item-name">{{ $item->produk->nama_produk }}</div>
                <table>
                    <tr>
                        <td>Qty</td>
                        <td class="val">{{ $item->kuantitas }} {{ $item->unit ?? 'Pcs' }}</td>
                    </tr>
                    <tr>
                        <td>Harga</td>
                        <td class="val">{{ format_rupiah($item->harga_satuan) }}</td>
                    </tr>
                    @if($item->diskon > 0)
                        <tr>
                            <td>Disc</td>
                            <td class="val">{{ $item->diskon }}%</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Batch</td>
                        <td class="val">{{ $item->batch_number ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Exp</td>
                        <td class="val">
                            {{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : 'N/A' }}
                        </td>
                    </tr>
                    @if($item->deskripsi)
                        <tr>
                            <td>Ket</td>
                            <td class="val">{{ $item->deskripsi }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><b>Jumlah</b></td>
                        <td class="val"><b>{{ format_rupiah($item->jumlah_baris) }}</b></td>
                    </tr>
                </table>
            </div>
        @endforeach

        <div class="divider"></div>

        <table>
            <tr>
                <td>Subtotal</td>
                <td class="val">{{ format_rupiah($subtotal) }}</td>
            </tr>
            @if(($pembelian->diskon_akhir ?? 0) > 0)
                <tr>
                    <td>Diskon</td>
                    <td class="val">- {{ format_rupiah($pembelian->diskon_akhir) }}</td>
                </tr>
            @endif
            @if(($pembelian->tax_percentage ?? 0) > 0)
                <tr>
                    <td>Pajak ({{ $pembelian->tax_percentage }}%)</td>
                    <td class="val">{{ format_rupiah($pajakNominal) }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand-total">GRAND TOTAL</td>
                <td class="val grand-total">{{ format_rupiah($pembelian->grand_total) }}</td>
            </tr>
        </table>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p>Scan untuk melihat dokumen</p>
        </div>

        <div class="footer">
            <p>marketing@hibiscusefsya.com</p>
            <p>-- Dokumen Internal --</p>
        </div>
    </div>
</body>

</html>
