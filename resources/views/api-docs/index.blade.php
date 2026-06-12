<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>API Docs - Hibiscus Efsya POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root{--brand:#9f1239;--brand-2:#be123c;--ink:#111827;--muted:#64748b;--line:#e5e7eb;--surface:#fff}
        body{font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:32px;background:#fff7f8;color:var(--ink)}
        h1{color:var(--brand);margin:0;font-size:28px}h2{color:#1f2937;margin:32px 0 12px}
        .shell{max-width:1120px;margin:0 auto}
        .hero{background:var(--surface);border:1px solid #f3d6dc;border-radius:16px;padding:28px;box-shadow:0 16px 45px rgba(136,19,55,.10)}
        .eyebrow{display:inline-block;margin-bottom:10px;padding:6px 10px;border-radius:999px;background:#fff1f4;color:var(--brand);font-size:12px;font-weight:800;letter-spacing:.08em}
        .meta{color:var(--muted);line-height:1.65}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px;margin:12px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}
        code{background:#f8fafc;border:1px solid #e2e8f0;padding:2px 6px;border-radius:5px;font-size:.875em}
        .badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:.75em;font-weight:800;margin-right:6px}
        .get{background:#dbeafe;color:#1d4ed8}.post{background:#dcfce7;color:#166534}
        .put{background:#fef9c3;color:#854d0e}.delete{background:#fee2e2;color:#991b1b}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
        th{background:#f8fafc;font-weight:700;color:#334155}
        .download-btns{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0 4px}
        .btn{padding:10px 16px;border-radius:999px;text-decoration:none;font-weight:700;font-size:.875rem}
        .btn-primary{background:var(--brand-2);color:#fff}.btn-secondary{background:#fff;border:1px solid #cbd5e1;color:#334155}
        @media (max-width:700px){body{padding:18px}.hero,.card{padding:18px}table{display:block;overflow-x:auto}}
    </style>
</head>
<body>
<main class="shell">
<section class="hero">
<span class="eyebrow">HE POS API</span>
<h1>Hibiscus Efsya POS API Documentation</h1>
<p class="meta">Base URL: <code>{{ $apiUrl }}</code></p>
<p class="meta">Auth: Bearer Token (SHA-256 hashed, 30 hari, header: <code>Authorization: Bearer {token}</code>)</p>

<div class="download-btns">
    <a href="{{ route('api.docs.download') }}" class="btn btn-primary">Download OpenAPI JSON</a>
    <a href="{{ route('api.docs.download.postman') }}" class="btn btn-secondary">Download Postman</a>
</div>
</section>

<h2>Authentication</h2>
<div class="card">
    <span class="badge post">POST</span> <code>/api/v1/login</code> - Login, dapatkan token<br>
    <span class="badge post">POST</span> <code>/api/v1/logout</code> - Logout, revoke token<br>
    <span class="badge get">GET</span> <code>/api/v1/profile</code> - Profile user<br>
    <span class="badge put">PUT</span> <code>/api/v1/profile</code> - Update profile<br>
    <span class="badge post">POST</span> <code>/api/v1/change-password</code> - Ganti password
</div>

<h2>Transaksi</h2>
<div class="card">
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Deskripsi</th></tr></thead>
        <tbody>
            @foreach([
                ['GET','penjualan','List penjualan'],['POST','penjualan','Buat penjualan'],
                ['GET','penjualan/{id}','Detail penjualan'],['PUT','penjualan/{id}','Update (super_admin)'],
                ['POST','penjualan/{id}/approve','Approve'],['POST','penjualan/{id}/cancel','Cancel'],
                ['POST','penjualan/{id}/mark-paid','Tandai Lunas'],['POST','penjualan/{id}/unmark-paid','Buka Lunas (super_admin)'],
                ['GET','pembelian','List pembelian'],['POST','pembelian','Buat pembelian'],
                ['GET','biaya','List biaya'],['POST','biaya','Buat biaya'],
                ['GET','kunjungan','List kunjungan'],['POST','kunjungan','Buat kunjungan'],
                ['GET','pembayaran','List pembayaran'],['POST','pembayaran','Buat pembayaran'],
                ['GET','penerimaan-barang','List penerimaan'],['POST','penerimaan-barang','Buat penerimaan'],
            ] as [$method, $path, $desc])
            <tr>
                <td><span class="badge {{ strtolower($method) }}">{{ $method }}</span></td>
                <td><code>/api/v1/{{ $path }}</code></td>
                <td>{{ $desc }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2>Master Data</h2>
<div class="card">
    <span class="badge get">GET</span> <code>/api/v1/gudang</code> - List gudang (role-scoped)<br>
    <span class="badge post">POST</span> <code>/api/v1/gudang/switch</code> - Switch gudang aktif<br>
    <span class="badge get">GET</span> <code>/api/v1/produk</code> - List produk<br>
    <span class="badge get">GET</span> <code>/api/v1/kontak</code> - List kontak<br>
    <span class="badge get">GET</span> <code>/api/v1/stok</code> - Stok per gudang<br>
    <span class="badge get">GET</span> <code>/api/v1/stok/log</code> - Riwayat stok (admin/super_admin)
</div>

<h2>Print & QR</h2>
<div class="card">
    <span class="badge get">GET</span> <code>/api/v1/print/{type}/{id}/qr</code> - QR data (type: penjualan/pembelian/biaya/kunjungan/pembayaran/penerimaan-barang)<br>
    <span class="badge get">GET</span> <code>/api/v1/print/{type}/{id}/bluetooth</code> - Bluetooth JSON (type: penjualan/pembelian/biaya/kunjungan)
</div>

<h2>Status Responses</h2>
<div class="card">
    <table>
        <tr><th>Code</th><th>Arti</th></tr>
        <tr><td><code>200</code></td><td>Berhasil</td></tr>
        <tr><td><code>201</code></td><td>Created (store)</td></tr>
        <tr><td><code>401</code></td><td>Unauthenticated (token missing/invalid/expired)</td></tr>
        <tr><td><code>403</code></td><td>Forbidden (role tidak punya akses)</td></tr>
        <tr><td><code>422</code></td><td>Validation error / business rule violation</td></tr>
        <tr><td><code>500</code></td><td>Server error (transaksi gagal)</td></tr>
    </table>
</div>
</main>
</body>
</html>
