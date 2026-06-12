<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Penerimaan Barang {{ $penerimaan->custom_number }}</title>
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

        .item-sku {
            font-size: 9px;
            color: #666;
        }

        .val {
            text-align: right;
        }

        .qty-diterima {
            color: #28a745;
            font-weight: bold;
        }

        .qty-reject {
            color: #dc3545;
            font-weight: bold;
        }

        .totals-box {
            padding: 8px;
            margin: 8px 0;
            border: 1px dashed #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
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
        $invoiceUrl = url('invoice/penerimaan-barang/' . $penerimaan->uuid);
        $totalDiterima = $penerimaan->items->sum('qty_diterima');
        $totalReject = $penerimaan->items->sum('qty_reject');
    @endphp

    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('assets/img/logoHE1.png') }}" class="logo" alt="Logo">
            <div class="title">PENERIMAAN BARANG</div>
        </div>

        <table>
            <tr>
                <td class="label-col">Nomor</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->custom_number }}</td>
            </tr>
            <tr>
                <td class="label-col">Tanggal</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->tgl_penerimaan->format('d/m/Y') }} |
                    {{ $penerimaan->created_at->format('H:i') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Status</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->status }}</td>
            </tr>
            <tr>
                <td class="label-col">Gudang</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->gudang->nama_gudang ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Dibuat oleh</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->user->name }}</td>
            </tr>
            <tr>
                <td class="label-col">Disetujui</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $penerimaan->approver->name ?? '-' }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <p style="font-weight: bold; margin-bottom: 5px;">Ref. Pembelian:</p>
        @if($penerimaan->pembelian)
            <p style="font-size: 10px;">{{ $penerimaan->pembelian->custom_number }}</p>
        @else
            <p style="font-size: 10px;">-</p>
        @endif

        <div class="divider"></div>

        <p style="font-weight: bold; margin-bottom: 5px;">Detail Barang:</p>
        @foreach($penerimaan->items as $item)
            <div style="margin-bottom: 8px;">
                <div class="item-name">{{ $item->produk->nama_produk ?? '-' }}</div>
                <div class="item-sku">{{ $item->produk->kode_produk ?? '-' }}</div>
                <div style="font-size: 9px; margin: 2px 0;">
                    @if($item->tipe_stok == 'gratis')
                        <span style="background: #28a745; color: #fff; padding: 1px 4px; border-radius: 2px;">Gratis</span>
                    @elseif($item->tipe_stok == 'sample')
                        <span style="background: #ffc107; color: #000; padding: 1px 4px; border-radius: 2px;">Sample</span>
                    @else
                        <span style="background: #4e73df; color: #fff; padding: 1px 4px; border-radius: 2px;">Penjualan</span>
                    @endif
                    @if($item->batch_number)
                        &nbsp;Batch: {{ $item->batch_number }}
                    @endif
                    @if($item->expired_date)
                        &nbsp;Exp: {{ $item->expired_date->format('d/m/Y') }}
                    @endif
                </div>
                <table>
                    <tr>
                        <td>Diterima</td>
                        <td class="val"><span class="qty-diterima">{{ $item->qty_diterima }}</span></td>
                    </tr>
                    @if($item->qty_reject > 0)
                        <tr>
                            <td>Reject</td>
                            <td class="val"><span class="qty-reject">{{ $item->qty_reject }}</span></td>
                        </tr>
                    @endif
                </table>
            </div>
        @endforeach

        <div class="divider"></div>

        <div class="totals-box">
            <table>
                <tr>
                    <td>Total Diterima</td>
                    <td class="val"><span class="qty-diterima">{{ $totalDiterima }} pcs</span></td>
                </tr>
                @if($totalReject > 0)
                    <tr>
                        <td>Total Reject</td>
                        <td class="val"><span class="qty-reject">{{ $totalReject }} pcs</span></td>
                    </tr>
                @endif
                <tr style="border-top: 1px dashed #000; padding-top: 5px;">
                    <td><b>Masuk Gudang</b></td>
                    <td class="val"><b>{{ $totalDiterima }} pcs</b></td>
                </tr>
            </table>
        </div>

        @if($penerimaan->keterangan)
            <div class="divider"></div>
            <p style="font-weight: bold; margin-bottom: 3px;">Keterangan:</p>
            <p>{{ $penerimaan->keterangan }}</p>
        @endif

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p>Scan untuk melihat dokumen</p>
        </div>

        <div class="footer">
            <p>marketing@hibiscusefsya.com</p>
            <p>-- Terima Kasih --</p>
        </div>
    </div>
</body>

</html>