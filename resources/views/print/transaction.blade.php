<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $record->nomor ?? '' }}</title>
    <style>
        @page {
            size: auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: #eef2f7;
        }

        body {
            margin: 0;
            color: #111;
            font-family: "Courier New", Courier, monospace;
            font-size: 10.5px;
            line-height: 1.18;
            background: transparent;
        }

        .screen-actions {
            display: flex;
            justify-content: center;
            padding: 10px 0;
        }

        .screen-actions button {
            border: 0;
            border-radius: 6px;
            padding: 8px 13px;
            color: #fff;
            background: #111827;
            font: 600 13px system-ui, sans-serif;
            cursor: pointer;
        }

        .receipt-paper {
            width: 58mm;
            margin: 0 auto 12px;
            padding: 2.5mm 2.3mm 3mm;
            background: #fff;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .18);
        }

        .receipt-logo {
            display: block;
            width: auto;
            max-width: 29mm;
            max-height: 12mm;
            margin: 0 auto 1mm;
            object-fit: contain;
        }

        .brand-name {
            text-align: center;
            font-size: 15px;
            line-height: 1.12;
            font-weight: 800;
        }

        .doc-title {
            margin-top: .7mm;
            text-align: center;
            font-size: 10.5px;
            line-height: 1.15;
            font-weight: 700;
            text-transform: uppercase;
        }

        .receipt-hr {
            height: 1px;
            margin: 1.6mm 0;
            border: 0;
            border-top: 1px dashed #111;
        }

        .receipt-body {
            margin: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            font: inherit;
        }

        .receipt-footer {
            text-align: center;
            font-size: 10px;
            line-height: 1.22;
        }

        .receipt-footer strong {
            font-weight: 800;
        }

        .dash {
            margin: .8mm 0;
            white-space: pre-wrap;
        }

        @media print {
            html,
            body {
                width: auto;
                min-height: 0;
                background: #fff;
            }

            .screen-actions {
                display: none;
            }

            .receipt-paper {
                width: 58mm;
                margin: 0 auto;
                padding: 2mm 2mm 2.5mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="screen-actions">
    <button type="button" onclick="window.print()">Print</button>
</div>

<main class="receipt-paper">
    <header>
        @if (file_exists(public_path('assets/img/logoHE1.png')))
            <img class="receipt-logo" src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya">
        @endif
        <div class="brand-name">HIBISCUS EFSYA</div>
        <div class="doc-title">{{ $receiptTitle ?? strtoupper($title) }}</div>
    </header>

    <hr class="receipt-hr">

    <pre class="receipt-body">{{ $receiptBody }}</pre>

    <hr class="receipt-hr">

    <footer class="receipt-footer">
        <strong>Periksa Invoice & Ambil Promo !</strong>
        <div class="dash">{{ $receiptDashLine }}</div>
        <div>customer.hibiscusefsya.com</div>
        <div class="dash">{{ $receiptDashLine }}</div>
        <div>marketing@hibiscusefsya.com</div>
        <div style="height: 1mm"></div>
        <div>Official WA Chat:</div>
        <div>+62 851-9555-0202</div>
        <div><strong>Terima kasih</strong></div>
    </footer>
</main>

<script>
    window.addEventListener('load', function () {
        if (new URLSearchParams(window.location.search).get('auto') === '1') {
            window.print();
        }
    });
</script>
</body>
</html>
