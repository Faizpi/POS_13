<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api;

/*
|--------------------------------------------------------------------------
| API Routes - Mobile API v1 (Compatible with Laravel 7 legacy)
|--------------------------------------------------------------------------
*/

// Public (no auth)
Route::prefix('v1')->group(function () {
    Route::post('login', [Api\AuthController::class, 'login']);
});

// Protected (require bearer token)
Route::prefix('v1')->middleware('api.token')->group(function () {

    // Auth
    Route::post('logout', [Api\AuthController::class, 'logout']);
    Route::get('profile', [Api\AuthController::class, 'profile']);
    Route::put('profile', [Api\AuthController::class, 'updateProfile']);
    Route::post('change-password', [Api\AuthController::class, 'changePassword']);
    Route::post('profile/avatar', [Api\AuthController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [Api\AuthController::class, 'deleteAvatar']);

    // Dashboard
    Route::get('dashboard', [Api\DashboardController::class, 'index']);
    Route::get('dashboard/daily-report', [Api\DashboardController::class, 'dailyReport']);
    Route::get('dashboard/daily-report/pdf', [Api\DashboardController::class, 'dailyReportPdf']);
    Route::get('dashboard/export/options', [Api\DashboardController::class, 'exportOptions']);
    Route::post('dashboard/export', [Api\DashboardController::class, 'export']);
    Route::get('lampiran/download', [Api\DashboardController::class, 'downloadLampiran']);

    // Print / QR
    Route::get('print/{type}/{id}/qr', [Api\PrintController::class, 'qrData']);
    Route::get('print/{type}/{id}/bluetooth', [Api\PrintController::class, 'bluetoothData']);

    // Gudang
    Route::get('gudang', [Api\GudangController::class, 'index']);
    Route::post('gudang/switch', [Api\GudangController::class, 'switchGudang']);
    Route::get('gudang/stok', [Api\GudangController::class, 'stok']);
    Route::get('gudang/stok-log', [Api\GudangController::class, 'stokLog']);
    Route::get('gudang/stok/export', [Api\GudangController::class, 'exportStok']);
    Route::post('gudang', [Api\GudangController::class, 'store']);
    Route::put('gudang/{id}', [Api\GudangController::class, 'update']);
    Route::delete('gudang/{id}', [Api\GudangController::class, 'destroy']);

    // Produk
    Route::get('produk/stok/{gudangId}', [Api\ProdukController::class, 'stokByGudang']);
    Route::get('produk', [Api\ProdukController::class, 'index']);
    Route::get('produk/{id}', [Api\ProdukController::class, 'show']);
    Route::post('produk', [Api\ProdukController::class, 'store']);
    Route::put('produk/{id}', [Api\ProdukController::class, 'update']);
    Route::delete('produk/{id}', [Api\ProdukController::class, 'destroy']);

    // Kontak
    Route::get('kontak', [Api\KontakController::class, 'index']);
    Route::get('kontak/{id}', [Api\KontakController::class, 'show']);
    Route::post('kontak', [Api\KontakController::class, 'store']);
    Route::put('kontak/{id}', [Api\KontakController::class, 'update']);
    Route::delete('kontak/{id}', [Api\KontakController::class, 'destroy']);

    // Penjualan
    Route::get('penjualan', [Api\PenjualanController::class, 'index']);
    Route::get('penjualan/{id}', [Api\PenjualanController::class, 'show']);
    Route::post('penjualan', [Api\PenjualanController::class, 'store']);
    Route::put('penjualan/{id}', [Api\PenjualanController::class, 'update']);
    Route::post('penjualan/{id}/approve', [Api\PenjualanController::class, 'approve']);
    Route::post('penjualan/{id}/cancel', [Api\PenjualanController::class, 'cancel']);
    Route::post('penjualan/{id}/uncancel', [Api\PenjualanController::class, 'uncancel']);
    Route::post('penjualan/{id}/mark-paid', [Api\PenjualanController::class, 'markAsPaid']);
    Route::post('penjualan/{id}/unmark-paid', [Api\PenjualanController::class, 'unmarkAsPaid']);

    // Pembelian
    Route::get('pembelian', [Api\PembelianController::class, 'index']);
    Route::get('pembelian/{id}', [Api\PembelianController::class, 'show']);
    Route::post('pembelian', [Api\PembelianController::class, 'store']);
    Route::put('pembelian/{id}', [Api\PembelianController::class, 'update']);
    Route::post('pembelian/{id}/approve', [Api\PembelianController::class, 'approve']);
    Route::post('pembelian/{id}/cancel', [Api\PembelianController::class, 'cancel']);
    Route::post('pembelian/{id}/uncancel', [Api\PembelianController::class, 'uncancel']);

    // Biaya
    Route::get('biaya', [Api\BiayaController::class, 'index']);
    Route::get('biaya/{id}', [Api\BiayaController::class, 'show']);
    Route::post('biaya', [Api\BiayaController::class, 'store']);
    Route::put('biaya/{id}', [Api\BiayaController::class, 'update']);
    Route::post('biaya/{id}/approve', [Api\BiayaController::class, 'approve']);
    Route::post('biaya/{id}/cancel', [Api\BiayaController::class, 'cancel']);
    Route::post('biaya/{id}/uncancel', [Api\BiayaController::class, 'uncancel']);

    // Kunjungan
    Route::get('kunjungan', [Api\KunjunganController::class, 'index']);
    Route::get('kunjungan/{id}', [Api\KunjunganController::class, 'show']);
    Route::post('kunjungan', [Api\KunjunganController::class, 'store']);
    Route::put('kunjungan/{id}', [Api\KunjunganController::class, 'update']);
    Route::post('kunjungan/{id}/approve', [Api\KunjunganController::class, 'approve']);
    Route::post('kunjungan/{id}/cancel', [Api\KunjunganController::class, 'cancel']);
    Route::post('kunjungan/{id}/uncancel', [Api\KunjunganController::class, 'uncancel']);

    // Pembayaran (Piutang)
    Route::get('pembayaran', [Api\PembayaranController::class, 'index']);
    Route::get('pembayaran/export-harian-pdf', [Api\PembayaranController::class, 'exportHarianPdf']);
    Route::get('pembayaran/penjualan-by-gudang/{gudangId}', [Api\PembayaranController::class, 'getPenjualanByGudang']);
    Route::get('pembayaran/penjualan-detail/{id}', [Api\PembayaranController::class, 'getPenjualanDetail']);
    Route::get('pembayaran/{id}', [Api\PembayaranController::class, 'show']);
    Route::post('pembayaran', [Api\PembayaranController::class, 'store']);
    Route::post('pembayaran/{id}/approve', [Api\PembayaranController::class, 'approve']);
    Route::post('pembayaran/{id}/cancel', [Api\PembayaranController::class, 'cancel']);
    Route::post('pembayaran/{id}/uncancel', [Api\PembayaranController::class, 'uncancel']);

    // Pembayaran Hutang
    Route::get('pembayaran-hutang', [Api\PembayaranController::class, 'indexHutang']);
    Route::get('pembayaran-hutang/pembelian-by-gudang/{gudangId}', [Api\PembayaranController::class, 'getPembelianByGudang']);
    Route::get('pembayaran-hutang/pembelian-detail/{id}', [Api\PembayaranController::class, 'getPembelianDetail']);
    Route::post('pembayaran-hutang', [Api\PembayaranController::class, 'storeHutang']);

    // Penerimaan Barang
    Route::get('penerimaan-barang/pembelian-by-gudang/{gudangId}', [Api\PenerimaanBarangController::class, 'getPembelianByGudang']);
    Route::get('penerimaan-barang/pembelian-detail/{id}', [Api\PenerimaanBarangController::class, 'getPembelianDetail']);
    Route::get('penerimaan-barang', [Api\PenerimaanBarangController::class, 'index']);
    Route::get('penerimaan-barang/{id}', [Api\PenerimaanBarangController::class, 'show']);
    Route::post('penerimaan-barang', [Api\PenerimaanBarangController::class, 'store']);
    Route::post('penerimaan-barang/{id}/approve', [Api\PenerimaanBarangController::class, 'approve']);
    Route::post('penerimaan-barang/{id}/cancel', [Api\PenerimaanBarangController::class, 'cancel']);
    Route::post('penerimaan-barang/{id}/uncancel', [Api\PenerimaanBarangController::class, 'uncancel']);

    // Stok
    Route::get('stok/log', [Api\StokController::class, 'log']);
    Route::get('stok', [Api\StokController::class, 'index']);
    Route::post('stok', [Api\StokController::class, 'store']);

    // Stock Opname
    Route::get('stock-opname', [Api\StockOpnameController::class, 'index']);
    Route::get('stock-opname/{id}', [Api\StockOpnameController::class, 'show']);
    Route::post('stock-opname', [Api\StockOpnameController::class, 'store']);
    Route::post('stock-opname/{id}/submit', [Api\StockOpnameController::class, 'submit']);
    Route::post('stock-opname/{id}/apply', [Api\StockOpnameController::class, 'apply']);

    // Neraca
    Route::get('neraca', [Api\NeracaController::class, 'index']);
    Route::get('neraca/export-pdf', [Api\NeracaController::class, 'exportPdf']);
    Route::get('neraca/export-excel', [Api\NeracaController::class, 'exportExcel']);

    // Piutang Dashboard
    Route::get('piutang', [Api\PiutangController::class, 'index']);
    Route::get('piutang/export-pdf', [Api\PiutangController::class, 'exportPdf']);

    // Hutang Dashboard
    Route::get('hutang', [Api\HutangController::class, 'index']);
    Route::get('hutang/export-pdf', [Api\HutangController::class, 'exportPdf']);

    // Catatan Hutang
    Route::get('catatan-hutang', [Api\CatatanHutangController::class, 'index']);

    // Tutup Buku
    Route::get('tutup-buku', [Api\TutupBukuController::class, 'index']);
    Route::post('tutup-buku/execute', [Api\TutupBukuController::class, 'execute']);
    Route::get('tutup-buku/backup-db', [Api\TutupBukuController::class, 'backupDb']);
    Route::get('tutup-buku/export-data', [Api\TutupBukuController::class, 'exportData']);

    // User Management
    Route::get('users', [Api\UserController::class, 'index']);
    Route::get('users/{id}', [Api\UserController::class, 'show']);
    Route::post('users', [Api\UserController::class, 'store']);
    Route::put('users/{id}', [Api\UserController::class, 'update']);
    Route::delete('users/{id}', [Api\UserController::class, 'destroy']);
});
