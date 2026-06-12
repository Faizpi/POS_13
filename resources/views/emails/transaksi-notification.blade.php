<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Transaksi - Hibiscus Efsya</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff7f8; margin: 0; padding: 24px 14px; color: #111827; }
        .container { max-width: 620px; margin: 0 auto; background: #fff; border: 1px solid #f3d6dc; border-radius: 14px; overflow: hidden; box-shadow: 0 18px 45px rgba(136, 19, 55, .12); }
        .header { background: #9f1239; color: #fff; padding: 28px 24px; text-align: center; }
        .brand-mark { display: inline-block; margin-bottom: 10px; padding: 7px 11px; border: 1px solid rgba(255,255,255,.35); border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .12em; }
        .header h1 { margin: 0; font-size: 21px; letter-spacing: .01em; }
        .body { padding: 28px; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .info-table { width: 100%; border-collapse: collapse; margin: 18px 0; border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; }
        .info-table td { padding: 10px 13px; border-bottom: 1px solid #eef2f7; }
        .info-table td:first-child { color: #64748b; width: 40%; background: #fbfdff; }
        .footer { padding: 18px 24px; background: #fff1f4; text-align: center; font-size: 12px; color: #9f1239; }
        .cta { display: inline-block; margin: 18px 0 4px; padding: 12px 22px; background: #be123c; color: #fff; text-decoration: none; border-radius: 999px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="brand-mark">HE POS</span>
            <h1>Hibiscus Efsya POS</h1>
            <p style="margin:4px 0 0; font-size:13px; opacity:.85;">Notifikasi Transaksi</p>
        </div>
        <div class="body">
            @php
                $typeLabel = match($type ?? '') {
                    'penjualan' => 'Penjualan',
                    'pembelian' => 'Pembelian',
                    'biaya' => 'Biaya',
                    'kunjungan' => 'Kunjungan',
                    default => 'Transaksi',
                };
                $notifLabel = match($notificationType ?? '') {
                    'created' => 'Transaksi Baru Dibuat',
                    'needs_approval' => 'Menunggu Persetujuan',
                    'approved' => 'Telah Disetujui',
                    default => 'Update',
                };
                $nomor = $transaksi->nomor ?? $transaksi->custom_number ?? '-';
            @endphp

            <h2 style="color:#111; margin:0 0 8px;">{{ $notifLabel }}</h2>
            <p style="color:#6b7280; margin:0 0 16px;">{{ $typeLabel }} #{{ $nomor }}</p>

            <table class="info-table">
                <tr><td>Nomor</td><td><strong>{{ $nomor }}</strong></td></tr>
                @if(isset($transaksi->tgl_transaksi) && $transaksi->tgl_transaksi)
                <tr><td>Tanggal</td><td>{{ $transaksi->tgl_transaksi->format('d M Y') }}</td></tr>
                @endif
                @if(isset($transaksi->tgl_kunjungan) && $transaksi->tgl_kunjungan)
                <tr><td>Tanggal</td><td>{{ $transaksi->tgl_kunjungan->format('d M Y') }}</td></tr>
                @endif
                <tr><td>Dibuat oleh</td><td>{{ $transaksi->user->name ?? '-' }}</td></tr>
                <tr><td>Gudang</td><td>{{ $transaksi->gudang->nama_gudang ?? '-' }}</td></tr>
                <tr><td>Status</td><td><span class="badge badge-{{ strtolower($transaksi->status ?? 'pending') }}">{{ $transaksi->status ?? '-' }}</span></td></tr>
                @if(isset($transaksi->grand_total))
                <tr><td>Total</td><td><strong>Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}</strong></td></tr>
                @endif
            </table>

            @if($transaksi->uuid ?? null)
            <div style="text-align:center;">
                <a href="{{ url('invoice/' . ($type ?? 'penjualan') . '/' . $transaksi->uuid) }}" class="cta">
                    Lihat Invoice
                </a>
            </div>
            @endif
        </div>
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem Hibiscus Efsya POS.<br>
            <a href="mailto:marketing@hibiscusefsya.com">marketing@hibiscusefsya.com</a></p>
        </div>
    </div>
</body>
</html>
