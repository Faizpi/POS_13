<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Bukti Pembayaran {{ $pembayaran->custom_number }}</title>
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

        .val {
            text-align: right;
        }

        .amount-box {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border: 1px dashed #000;
        }

        .amount-label {
            font-size: 10px;
            margin-bottom: 3px;
        }

        .amount-value {
            font-size: 16px;
            font-weight: bold;
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
        $invoiceUrl = url('invoice/pembayaran/' . $pembayaran->uuid);
    @endphp

    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('assets/img/logoHE1.png') }}" class="logo" alt="Logo">
            <div class="title">BUKTI PEMBAYARAN</div>
        </div>

        <table>
            <tr>
                <td class="label-col">Nomor</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->custom_number }}</td>
            </tr>
            <tr>
                <td class="label-col">Tanggal</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->tgl_pembayaran->format('d/m/Y') }} |
                    {{ $pembayaran->created_at->format('H:i') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Metode</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->metode_pembayaran }}</td>
            </tr>
            <tr>
                <td class="label-col">Status</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->status }}</td>
            </tr>
            <tr>
                <td class="label-col">Dibuat oleh</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->user->name }}</td>
            </tr>
            <tr>
                <td class="label-col">Disetujui</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->approver->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Gudang</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $pembayaran->gudang->nama_gudang ?? '-' }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <p style="font-weight: bold; margin-bottom: 5px;">Referensi Invoice:</p>
        @if($pembayaran->penjualan)
            <table>
                <tr>
                    <td class="label-col">No. Invoice</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $pembayaran->penjualan->custom_number }}</td>
                </tr>
                <tr>
                    <td class="label-col">Pelanggan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $pembayaran->penjualan->pelanggan ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label-col">Total Invoice</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ format_rupiah($pembayaran->penjualan->grand_total) }}</td>
                </tr>
            </table>
        @else
            <p>Invoice tidak tersedia</p>
        @endif

        <div class="amount-box">
            <div class="amount-label">JUMLAH PEMBAYARAN</div>
            <div class="amount-value">{{ format_rupiah($pembayaran->jumlah_bayar) }}</div>
        </div>

        @if($pembayaran->keterangan)
            <div class="divider"></div>
            <p style="font-weight: bold; margin-bottom: 3px;">Keterangan:</p>
            <p>{{ $pembayaran->keterangan }}</p>
        @endif

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p>Scan untuk melihat bukti pembayaran</p>
        </div>

        <div class="footer">
            <p>marketing@hibiscusefsya.com</p>
            <p>-- Terima Kasih --</p>
        </div>
    </div>
</body>

</html>