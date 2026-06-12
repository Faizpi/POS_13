<!DOCTYPE html>
<html lang="id">
@php
    $dateCode = $pembelian->created_at->format('Ymd');
    $noUrut = str_pad($pembelian->no_urut_harian, 3, '0', STR_PAD_LEFT);
    $nomorPembelian = "REQ-{$pembelian->user_id}-{$dateCode}-{$noUrut}";
@endphp

<head>
    <meta charset="UTF-8">
    <title>Invoice Pembelian - {{ $nomorPembelian }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }

        .logo {
            font-size: 18pt;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 2px;
        }

        .tagline {
            font-size: 8pt;
            color: #666;
        }

        .invoice-title {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 10px;
            color: #333;
        }

        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .info-left,
        .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-table {
            width: 100%;
            font-size: 9pt;
        }

        .info-table td {
            padding: 3px 0;
        }

        .info-table .label {
            width: 40%;
            color: #666;
        }

        .info-table .value {
            font-weight: 500;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .items-table th {
            background: #007bff;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 9pt;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .items-table td {
            padding: 8px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .total-section {
            width: 100%;
            margin-top: 15px;
        }

        .total-table {
            float: right;
            width: 45%;
            font-size: 10pt;
        }

        .total-table td {
            padding: 5px 0;
        }

        .total-table td:last-child {
            text-align: right;
        }

        .total-table .grand-total {
            font-weight: bold;
            font-size: 12pt;
            border-top: 2px solid #007bff;
            padding-top: 8px;
            color: #007bff;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }

        .status-approved,
        .status-lunas {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-canceled {
            background: #f8d7da;
            color: #721c24;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .memo-section {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .memo-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 5px;
        }

        .memo-content {
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>

<body>
    <!-- HEADER -->
    <div class="header">
        <div class="logo">HIBISCUS EFSYA</div>
        <div class="tagline">marketing@hibiscusefsya.com</div>
        <div class="invoice-title">PERMINTAAN PEMBELIAN</div>
    </div>

    <!-- INFO SECTION -->
    <div class="info-section">
        <div class="info-left">
            <table class="info-table">
                <tr>
                    <td class="label">No. Pembelian</td>
                    <td class="value">: {{ $nomorPembelian }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Transaksi</td>
                    <td class="value">: {{ $pembelian->tgl_transaksi->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Jatuh Tempo</td>
                    <td class="value">:
                        {{ $pembelian->tgl_jatuh_tempo ? $pembelian->tgl_jatuh_tempo->format('d/m/Y') : '-' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Syarat Pembayaran</td>
                    <td class="value">: {{ $pembelian->syarat_pembayaran ?? '-' }}</td>
                </tr>
                @if($pembelian->urgensi)
                    <tr>
                        <td class="label">Urgensi</td>
                        <td class="value">: {{ $pembelian->urgensi }}</td>
                    </tr>
                @endif
                @if($pembelian->tahun_anggaran)
                    <tr>
                        <td class="label">Thn Anggaran</td>
                        <td class="value">: {{ $pembelian->tahun_anggaran }}</td>
                    </tr>
                @endif
            </table>
        </div>
        <div class="info-right">
            <table class="info-table">
                <tr>
                    <td class="label">Vendor</td>
                    <td class="value">: {{ $pembelian->staf_penyetuju ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Pembuat</td>
                    <td class="value">: {{ $pembelian->user->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Gudang</td>
                    <td class="value">: {{ $pembelian->gudang->nama_gudang ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value">:
                        <span class="status-badge status-{{ strtolower($pembelian->status) }}">
                            {{ $pembelian->status }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 35%">Produk</th>
                <th style="width: 10%">Qty</th>
                <th style="width: 15%">Harga</th>
                <th style="width: 15%">Diskon</th>
                <th style="width: 20%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pembelian->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->produk->nama_produk ?? '-' }} <br>
                        <small style="color:#999">({{ $item->produk->item_code ?? '-' }})</small><br>
                        <small style="color:#666">Batch: {{ $item->batch_number ?? 'N/A' }} | Exp:
                            {{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : 'N/A' }}</small>
                        @if($item->deskripsi)
                            <br><small style="color:#666">Ket: {{ $item->deskripsi }}</small>
                        @endif
                    </td>
                    <td>{{ $item->kuantitas }} {{ $item->unit ?? 'Pcs' }}</td>
                    <td>{{ format_rupiah($item->harga_satuan) }}</td>
                    <td>{{ format_rupiah($item->diskon_per_item ?? 0) }}</td>
                    <td>{{ format_rupiah($item->jumlah_baris) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- TOTAL SECTION -->
    <div class="total-section clearfix">
        <table class="total-table">
            @php
                $subtotal = $pembelian->items->sum('jumlah_baris');
                $kenaPajak = max(0, $subtotal - ($pembelian->diskon_akhir ?? 0));
                $pajakNominal = $pembelian->tax_percentage > 0 ? $kenaPajak * ($pembelian->tax_percentage / 100) : 0;
            @endphp
            <tr>
                <td>Subtotal</td>
                <td>{{ format_rupiah($subtotal) }}</td>
            </tr>
            @if($pembelian->diskon_akhir > 0)
                <tr>
                    <td>Diskon Akhir</td>
                    <td>- {{ format_rupiah($pembelian->diskon_akhir) }}</td>
                </tr>
            @endif
            @if($pembelian->tax_percentage > 0)
                <tr>
                    <td>Pajak ({{ $pembelian->tax_percentage }}%)</td>
                    <td>{{ format_rupiah($pajakNominal) }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand-total">GRAND TOTAL</td>
                <td class="grand-total">{{ format_rupiah($pembelian->grand_total) }}</td>
            </tr>
        </table>
    </div>

    @if($pembelian->memo)
        <div class="memo-section">
            <div class="memo-title">Catatan:</div>
            <div class="memo-content">{{ $pembelian->memo }}</div>
        </div>
    @endif

    @if($pembelian->approver)
        <div style="margin-top: 20px; font-size: 9pt;">
            <strong>Disetujui oleh:</strong> {{ $pembelian->approver->name }}
        </div>
    @endif

    <!-- FOOTER -->
    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem.</p>
        <p>marketing@hibiscusefsya.com</p>
    </div>
</body>

</html>
