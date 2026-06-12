<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>{{ strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'Bukti Pemasukan' : 'Bukti Pengeluaran' }}
        {{ $biaya->penerima }}
    </title>
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
        $nomorInvoice = $biaya->nomor ?? $biaya->custom_number ?? ('EXP-' . $biaya->id);
        $invoiceUrl = url('invoice/biaya/' . $biaya->uuid);

        $subtotal = $biaya->items->sum('jumlah');
        $pajakNominal = $subtotal * (($biaya->tax_percentage ?? 0) / 100);
    @endphp

    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('assets/img/logoHE1.png') }}" class="logo" alt="Logo">
            <div class="title">
                {{ strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'BUKTI PEMASUKAN' : 'BUKTI PENGELUARAN' }}
            </div>
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
                <td class="value-col">{{ $biaya->tgl_transaksi->format('d/m/Y') }} |
                    {{ $biaya->created_at->format('H:i') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Pembayaran</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->cara_pembayaran ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Jenis</td>
                <td class="colon-col">:</td>
                <td class="value-col">
                    {{ strtolower($biaya->jenis_biaya ?? 'keluar') === 'masuk' ? 'Biaya Masuk' : 'Biaya Keluar' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Bayar Dari</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->bayar_dari ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Penerima</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->penerima ?? '-' }}</td>
            </tr>
            @if($biaya->alamat_penagihan)
                <tr>
                    <td class="label-col">Alamat</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $biaya->alamat_penagihan }}</td>
                </tr>
            @endif
            <tr>
                <td class="label-col">Gudang</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ optional($biaya->gudang)->nama_gudang ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Pembuat</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->user->name }}</td>
            </tr>
            <tr>
                <td class="label-col">Disetujui</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->status == 'Pending' ? '-' : ($biaya->approver->name ?? '-') }}</td>
            </tr>
            <tr>
                <td class="label-col">Status</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $biaya->status }}</td>
            </tr>
            @if($biaya->tag)
                <tr>
                    <td class="label-col">Tag</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $biaya->tag }}</td>
                </tr>
            @endif
            @if($biaya->koordinat)
                <tr>
                    <td class="label-col">Koordinat</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $biaya->koordinat }}</td>
                </tr>
            @endif
            @if($biaya->memo)
                <tr>
                    <td class="label-col">Memo</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $biaya->memo }}</td>
                </tr>
            @endif
        </table>

        <div class="divider"></div>

        @foreach($biaya->items as $item)
            <div style="margin-bottom: 8px;">
                <div class="item-name">{{ $item->kategori }}</div>
                <table>
                    @if($item->deskripsi)
                        <tr>
                            <td>Ket</td>
                            <td class="val">{{ $item->deskripsi }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><b>Jumlah</b></td>
                        <td class="val"><b>{{ format_rupiah($item->jumlah) }}</b></td>
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
            @if(($biaya->tax_percentage ?? 0) > 0)
                <tr>
                    <td>Pajak ({{ $biaya->tax_percentage }}%)</td>
                    <td class="val">{{ format_rupiah($pajakNominal) }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand-total">GRAND TOTAL</td>
                <td class="val grand-total">{{ format_rupiah($biaya->grand_total) }}</td>
            </tr>
        </table>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p>Scan untuk melihat bukti</p>
        </div>

        <div class="footer">
            <p>marketing@hibiscusefsya.com</p>
            <p>-- Terima Kasih --</p>
        </div>
    </div>
</body>

</html>
