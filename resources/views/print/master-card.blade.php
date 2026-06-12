<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; color: #111827; background: #f9fafb; }
        main { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 28px; }
        h1 { margin: 0 0 6px; font-size: 26px; }
        .code { display: inline-block; margin: 8px 0 20px; padding: 6px 10px; border-radius: 999px; background: #f3f4f6; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 28px; margin-bottom: 24px; }
        .label { display: block; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .value { display: block; margin-top: 4px; font-weight: 600; }
        .codes { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center; margin-top: 18px; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; text-align: center; }
        .barcode { max-width: 280px; width: 100%; height: auto; }
        .qr { max-width: 160px; width: 100%; height: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; color: #374151; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .actions { margin-top: 24px; text-align: right; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; color: #fff; background: #111827; cursor: pointer; }
        @media print {
            body { padding: 0; background: #fff; }
            main { border: 0; border-radius: 0; }
            .actions { display: none; }
        }
        @media (max-width: 700px) {
            body { padding: 12px; }
            main { padding: 16px; }
            .grid, .codes { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    @if ($type === 'produk')
        @php
            $code = $record->item_code ?: 'PRD' . $record->id;
            $qrData = "PRODUK\nKode: {$code}\nNama: {$record->nama_produk}";
        @endphp
        <h1>{{ $record->nama_produk }}</h1>
        <span class="code">{{ $code }}</span>
        <div class="grid">
            <div><span class="label">Harga Retail</span><span class="value">{{ format_rupiah($record->harga) }}</span></div>
            <div><span class="label">Harga Grosir</span><span class="value">{{ format_rupiah($record->harga_grosir) }}</span></div>
            <div><span class="label">Satuan</span><span class="value">{{ $record->satuan }}</span></div>
            <div><span class="label">Dibuat</span><span class="value">{{ $record->created_at?->format('d/m/Y H:i') ?? '-' }}</span></div>
            <div style="grid-column: 1 / -1;"><span class="label">Deskripsi</span><span class="value">{{ $record->deskripsi ?: '-' }}</span></div>
        </div>
        <h2>Stok per Gudang</h2>
        <table>
            <thead><tr><th>Gudang</th><th>Stok</th><th>Penjualan</th><th>Gratis</th><th>Sample</th></tr></thead>
            <tbody>
            @forelse ($record->stokDiGudang as $stok)
                <tr>
                    <td>{{ $stok->gudang?->nama_gudang ?? '-' }}</td>
                    <td>{{ number_format((float) $stok->stok, 0, ',', '.') }}</td>
                    <td>{{ number_format((float) $stok->stok_penjualan, 0, ',', '.') }}</td>
                    <td>{{ number_format((float) $stok->stok_gratis, 0, ',', '.') }}</td>
                    <td>{{ number_format((float) $stok->stok_sample, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada stok.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        @php
            $code = $record->kode_kontak ?: 'KONTAK' . $record->id;
            $qrData = "KONTAK\nKode: {$code}\nNama: {$record->nama}\nTelp: {$record->no_telp}";
        @endphp
        <h1>{{ $record->nama }}</h1>
        <span class="code">{{ $code }}</span>
        <div class="grid">
            <div><span class="label">Email</span><span class="value">{{ $record->email ?: '-' }}</span></div>
            <div><span class="label">No Telepon</span><span class="value">{{ receipt_format_phone($record->no_telp) ?: '-' }}</span></div>
            <div><span class="label">Diskon</span><span class="value">{{ rtrim(rtrim(number_format((float) $record->diskon_persen, 2, ',', '.'), '0'), ',') }}%</span></div>
            <div><span class="label">Gudang</span><span class="value">{{ $record->gudang?->nama_gudang ?? '-' }}</span></div>
            <div style="grid-column: 1 / -1;"><span class="label">Alamat</span><span class="value">{{ $record->alamat ?: '-' }}</span></div>
        </div>
    @endif

    <div class="codes">
        <div class="box">
            <span class="label">Barcode</span>
            <img class="barcode" src="https://barcodeapi.org/api/128/{{ urlencode($code) }}" alt="Barcode {{ $code }}">
            <div class="value">{{ $code }}</div>
        </div>
        <div class="box">
            <span class="label">QR Code</span>
            <img class="qr" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrData) }}" alt="QR Code">
        </div>
    </div>

    @empty($pdf)
        <div class="actions">
            <button type="button" onclick="window.print()">Print</button>
        </div>
    @endempty
</main>
</body>
</html>
