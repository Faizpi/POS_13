<!DOCTYPE html>
<html lang="id">
@php
    $dateCode = $biaya->created_at->format('Ymd');
    $noUrut = str_pad($biaya->no_urut_harian, 3, '0', STR_PAD_LEFT);
    $nomorInvoice = "EXP-{$biaya->user_id}-{$dateCode}-{$noUrut}";
@endphp

<head>
    <meta charset="UTF-8">
    <title>Invoice Biaya - {{ $nomorInvoice }}</title>
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
            border-bottom: 2px solid
                {{ $biaya->jenis_biaya == 'masuk' ? '#28a745' : '#dc3545' }}
            ;
            padding-bottom: 15px;
        }

        .logo {
            font-size: 18pt;
            font-weight: bold;
            color:
                {{ $biaya->jenis_biaya == 'masuk' ? '#28a745' : '#dc3545' }}
            ;
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

        .jenis-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 10pt;
            font-weight: bold;
            margin-top: 8px;
        }

        .jenis-masuk {
            background: #d4edda;
            color: #155724;
        }

        .jenis-keluar {
            background: #f8d7da;
            color: #721c24;
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
            background:
                {{ $biaya->jenis_biaya == 'masuk' ? '#28a745' : '#dc3545' }}
            ;
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
            border-top: 2px solid
                {{ $biaya->jenis_biaya == 'masuk' ? '#28a745' : '#dc3545' }}
            ;
            padding-top: 8px;
            color:
                {{ $biaya->jenis_biaya == 'masuk' ? '#28a745' : '#dc3545' }}
            ;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }

        .status-approved {
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
        <div class="invoice-title">{{ $biaya->jenis_biaya == 'masuk' ? 'BUKTI PEMASUKAN' : 'BUKTI PENGELUARAN' }}</div>
        <div class="jenis-badge jenis-{{ $biaya->jenis_biaya }}">
            {{ $biaya->jenis_biaya == 'masuk' ? 'BIAYA MASUK' : 'BIAYA KELUAR' }}
        </div>
    </div>

    <!-- INFO SECTION -->
    <div class="info-section">
        <div class="info-left">
            <table class="info-table">
                <tr>
                    <td class="label">No. Transaksi</td>
                    <td class="value">: {{ $nomorInvoice }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Transaksi</td>
                    <td class="value">: {{ $biaya->tgl_transaksi->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Cara Pembayaran</td>
                    <td class="value">: {{ $biaya->cara_pembayaran ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Bayar Dari</td>
                    <td class="value">: {{ $biaya->bayar_dari ?? '-' }}</td>
                </tr>
                @if($biaya->tag)
                    <tr>
                        <td class="label">Tag</td>
                        <td class="value">: {{ $biaya->tag }}</td>
                    </tr>
                @endif
                @if($biaya->koordinat)
                    <tr>
                        <td class="label">Koordinat</td>
                        <td class="value">: {{ $biaya->koordinat }}</td>
                    </tr>
                @endif
            </table>
        </div>
        <div class="info-right">
            <table class="info-table">
                <tr>
                    <td class="label">Penerima</td>
                    <td class="value">: {{ $biaya->penerima ?? '-' }}</td>
                </tr>
                @if($biaya->alamat_penagihan)
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="value">: {{ $biaya->alamat_penagihan }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">Gudang</td>
                    <td class="value">: {{ optional($biaya->gudang)->nama_gudang ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Pembuat</td>
                    <td class="value">: {{ $biaya->user->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value">:
                        <span class="status-badge status-{{ strtolower($biaya->status) }}">
                            {{ $biaya->status }}
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
                <th style="width: 30%">Kategori</th>
                <th style="width: 45%">Deskripsi</th>
                <th style="width: 20%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($biaya->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->kategori ?? '-' }}</td>
                    <td>{{ $item->deskripsi ?? '-' }}</td>
                    <td>{{ format_rupiah($item->jumlah) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- TOTAL SECTION -->
    <div class="total-section clearfix">
        <table class="total-table">
            @php
                $subtotal = $biaya->items->sum('jumlah');
                $kenaPajak = max(0, $subtotal);
                $pajakNominal = $biaya->tax_percentage > 0 ? $kenaPajak * ($biaya->tax_percentage / 100) : 0;
            @endphp
            <tr>
                <td>Subtotal</td>
                <td>{{ format_rupiah($subtotal) }}</td>
            </tr>
            @if($biaya->tax_percentage > 0)
                <tr>
                    <td>Pajak ({{ $biaya->tax_percentage }}%)</td>
                    <td>{{ format_rupiah($pajakNominal) }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand-total">GRAND TOTAL</td>
                <td class="grand-total">{{ format_rupiah($biaya->grand_total) }}</td>
            </tr>
        </table>
    </div>

    @if($biaya->memo)
        <div class="memo-section">
            <div class="memo-title">Catatan:</div>
            <div class="memo-content">{{ $biaya->memo }}</div>
        </div>
    @endif

    @if($biaya->approver)
        <div style="margin-top: 20px; font-size: 9pt;">
            <strong>Disetujui oleh:</strong> {{ $biaya->approver->name }}
        </div>
    @endif

    <!-- FOOTER -->
    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem.</p>
        <p>marketing@hibiscusefsya.com</p>
    </div>
</body>

</html>
