<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - Hibiscus Efsya</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff7f8; margin: 0; padding: 24px 14px; color: #111827; }
        .container { max-width: 620px; margin: 0 auto; background: #fff; border: 1px solid #f3d6dc; border-radius: 14px; overflow: hidden; box-shadow: 0 18px 45px rgba(136, 19, 55, .12); }
        .header { background: #9f1239; color: #fff; padding: 28px 24px; text-align: center; }
        .brand-mark { display: inline-block; margin-bottom: 10px; padding: 7px 11px; border: 1px solid rgba(255,255,255,.35); border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .12em; }
        .body { padding: 28px; }
        .body h2 { margin-top: 0; color: #111827; }
        .body p { color: #475569; line-height: 1.65; }
        .footer { padding: 18px; background: #fff1f4; text-align: center; font-size: 12px; color: #9f1239; }
        .cta { display: inline-block; margin: 18px 0 4px; padding: 12px 22px; background: #be123c; color: #fff; text-decoration: none; border-radius: 999px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="brand-mark">HE POS</span>
            <h1 style="margin:0; font-size:21px;">Hibiscus Efsya POS</h1>
        </div>
        <div class="body">
            <h2>Invoice Terlampir</h2>
            <p>Terlampir adalah invoice untuk transaksi Anda. Silakan periksa detail invoice pada file PDF yang terlampir.</p>

            @if($transaksi->uuid ?? null)
            <div style="text-align:center;">
                <a href="{{ url('invoice/' . ($type ?? 'penjualan') . '/' . $transaksi->uuid) }}" class="cta">
                    Lihat Online
                </a>
            </div>
            @endif
        </div>
        <div class="footer">
            <p>Hibiscus Efsya POS | <a href="mailto:marketing@hibiscusefsya.com">marketing@hibiscusefsya.com</a></p>
        </div>
    </div>
</body>
</html>
