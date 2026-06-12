<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kunjungan {{ $kunjungan->sales_nama }}</title>
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
        $nomorInvoice = $kunjungan->nomor ?? $kunjungan->custom_number ?? ('VST-' . $kunjungan->id);
        $invoiceUrl = url('invoice/kunjungan/' . $kunjungan->uuid);
    @endphp

    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('assets/img/logoHE1.png') }}" class="logo" alt="Logo">
            <div class="title">BUKTI KUNJUNGAN</div>
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
                <td class="value-col">{{ $kunjungan->tgl_kunjungan->format('d/m/Y') }} |
                    {{ $kunjungan->created_at->format('H:i') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Tujuan</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->tujuan }}</td>
            </tr>
            <tr>
                <td class="label-col">Pelanggan</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->sales_nama }}</td>
            </tr>
            @if($kunjungan->sales_no_telepon)
                <tr>
                    <td class="label-col">No. Telepon</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ receipt_format_phone($kunjungan->sales_no_telepon) }}</td>
                </tr>
            @endif
            @if($kunjungan->sales_alamat)
                <tr>
                    <td class="label-col">Alamat</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $kunjungan->sales_alamat }}</td>
                </tr>
            @endif
            <tr>
                <td class="label-col">Pembuat</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->user->name }}</td>
            </tr>
            <tr>
                <td class="label-col">Disetujui</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->status == 'Pending' ? '-' : ($kunjungan->approver->name ?? '-') }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Gudang</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->gudang->nama_gudang ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-col">Status</td>
                <td class="colon-col">:</td>
                <td class="value-col">{{ $kunjungan->status }}</td>
            </tr>
            @if($kunjungan->koordinat)
                <tr>
                    <td class="label-col">Koordinat</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $kunjungan->koordinat }}</td>
                </tr>
            @endif
        </table>

        @if($kunjungan->memo)
            <div class="divider"></div>
            <div style="font-size: 10px; margin-bottom: 5px;"><strong>Catatan / Memo:</strong></div>
            <div style="font-size: 10px;">{{ $kunjungan->memo }}</div>
        @endif

        @if($kunjungan->items && $kunjungan->items->count() > 0)
            <div class="divider"></div>
            <div style="font-size: 10px; margin-bottom: 5px; font-weight: bold;">Detail Item:</div>

            @foreach($kunjungan->items as $item)
                @php
                    $tipeStok = $item->tipe_stok ?? 'penjualan';
                    if ($kunjungan->tujuan === 'Promo Gratis') {
                        $tipeStok = 'gratis';
                    } elseif ($kunjungan->tujuan === 'Promo Sample') {
                        $tipeStok = 'sample';
                    }
                @endphp
                <div style="margin-bottom: 8px;">
                    <div class="item-name">{{ $item->produk->nama_produk ?? 'Item Hapus' }}</div>
                    <table>
                        <tr>
                            <td>Qty</td>
                            <td class="val">{{ $item->jumlah ?? 0 }} {{ optional($item->produk)->satuan ?? 'Pcs' }}</td>
                        </tr>
                        <tr>
                            <td>Tipe</td>
                            <td class="val">{{ $tipeStok }}</td>
                        </tr>
                        <tr>
                            <td>Batch</td>
                            <td class="val">{{ $item->batch_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td>Exp</td>
                            <td class="val">{{ $item->expired_date ? $item->expired_date->format('d/m/Y') : 'N/A' }}</td>
                        </tr>
                        @if($item->keterangan)
                            <tr>
                                <td>Ket</td>
                                <td class="val">{{ $item->keterangan }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            @endforeach
        @endif

        <div class="divider"></div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($invoiceUrl) }}"
                alt="QR Code">
            <p>Scan untuk melihat detail</p>
        </div>

        <div class="footer">
            <p>marketing@hibiscusefsya.com</p>
            <p>-- Terima Kasih --</p>
        </div>
    </div>
</body>

</html>
