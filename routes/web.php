<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocController;
use App\Http\Controllers\BluetoothPrintController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PublicDocumentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Root redirect to app panel (login if unauth)
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/app');
    }
    return redirect('/app/login');
});

// ========================================================================
// API DOCUMENTATION (Public)
// ========================================================================
Route::get('/docs', [ApiDocController::class, 'index'])->name('api.docs');
Route::get('/docs/json', [ApiDocController::class, 'json'])->name('api.docs.json');
Route::get('/docs/download', [ApiDocController::class, 'download'])->name('api.docs.download');
Route::get('/docs/download/postman', [ApiDocController::class, 'downloadPostman'])->name('api.docs.download.postman');

// ========================================================================
// CUSTOMER PORTAL (Login via No Telp + PIN)
// Session keys: customer_id, customer_no_telp, customer_nama
// ========================================================================
Route::prefix('customer')->name('customer.')->group(function () {
    // Public (unauthenticated)
    Route::get('/', [CustomerPortalController::class, 'loginForm'])->name('login');
    Route::get('login', [CustomerPortalController::class, 'loginForm']);
    Route::post('check-phone', [CustomerPortalController::class, 'checkPhone'])->name('check.phone');
    Route::post('login', [CustomerPortalController::class, 'login'])->name('login.submit');
    Route::post('logout', [CustomerPortalController::class, 'logout'])->name('logout');

    // Authenticated (requires customer.auth session)
    Route::middleware(['customer.auth'])->group(function () {
        Route::get('dashboard', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('history', [CustomerPortalController::class, 'history'])->name('history');
        Route::get('history/{id}', [CustomerPortalController::class, 'historyDetail'])->name('history.detail');
        Route::get('kunjungan', [CustomerPortalController::class, 'kunjungan'])->name('kunjungan');
        Route::get('kunjungan/{id}', [CustomerPortalController::class, 'kunjunganDetail'])->name('kunjungan.detail');
    });
});

// ========================================================================
// BLUETOOTH PRINT JSON ENDPOINTS (Authenticated)
// Used by public/js/bluetooth-print.js for thermal printing
// ========================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('bluetooth/penjualan/{id}', [BluetoothPrintController::class, 'penjualanJson'])->name('bluetooth.penjualan');
    Route::get('bluetooth/pembelian/{id}', [BluetoothPrintController::class, 'pembelianJson'])->name('bluetooth.pembelian');
    Route::get('bluetooth/biaya/{id}', [BluetoothPrintController::class, 'biayaJson'])->name('bluetooth.biaya');
    Route::get('bluetooth/kunjungan/{id}', [BluetoothPrintController::class, 'kunjunganJson'])->name('bluetooth.kunjungan');

    Route::get('penjualan/{penjualan}/print', [PublicDocumentController::class, 'printPenjualan'])->name('penjualan.print');
    Route::get('penjualan/{penjualan}/print-rich', [PrintController::class, 'penjualanRichText'])->name('penjualan.printRich');
    Route::get('pembelian/{pembelian}/print', [PublicDocumentController::class, 'printPembelian'])->name('pembelian.print');
    Route::get('pembelian/{pembelian}/print-rich', [PrintController::class, 'pembelianRichText'])->name('pembelian.printRich');
    Route::get('biaya/{biaya}/print', [PublicDocumentController::class, 'printBiaya'])->name('biaya.print');
    Route::get('biaya/{biaya}/print-rich', [PrintController::class, 'biayaRichText'])->name('biaya.printRich');
    Route::get('kunjungan/{kunjungan}/print', [PublicDocumentController::class, 'printKunjungan'])->name('kunjungan.print');
    Route::get('pembayaran/{pembayaran}/print', [PublicDocumentController::class, 'printPembayaran'])->name('pembayaran.print');
    Route::get('penerimaan-barang/{penerimaanBarang}/print', [PublicDocumentController::class, 'printPenerimaanBarang'])->name('penerimaan-barang.print');

    Route::get('produk/{produk}/print', [PublicDocumentController::class, 'printProduk'])->name('produk.print');
    Route::get('produk/{produk}/download', [PublicDocumentController::class, 'downloadProduk'])->name('produk.download');
    Route::get('kontak/{kontak}/print', [PublicDocumentController::class, 'printKontak'])->name('kontak.print');
    Route::get('kontak/{kontak}/download', [PublicDocumentController::class, 'downloadKontak'])->name('kontak.download');

    // ===== DELETE LAMPIRAN =====
    Route::delete('penjualan/{penjualan}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranPenjualan'])->name('penjualan.deleteLampiran');
    Route::delete('pembelian/{pembelian}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranPembelian'])->name('pembelian.deleteLampiran');
    Route::delete('biaya/{biaya}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranBiaya'])->name('biaya.deleteLampiran');
    Route::delete('kunjungan/{kunjungan}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranKunjungan'])->name('kunjungan.deleteLampiran');
    Route::delete('pembayaran/{pembayaran}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranPembayaran'])->name('pembayaran.deleteLampiran');
    Route::delete('penerimaan-barang/{penerimaanBarang}/lampiran/{index}', [PublicDocumentController::class, 'deleteLampiranPenerimaanBarang'])->name('penerimaan-barang.deleteLampiran');
});

// ========================================================================
// PUBLIC INVOICE (No Auth - UUID based for QR Code access)
// ========================================================================
Route::prefix('invoice')->name('public.invoice.')->group(function () {
    Route::get('penjualan/{uuid}', [PublicDocumentController::class, 'invoicePenjualan'])->name('penjualan');
    Route::get('penjualan/{uuid}/download', [PublicDocumentController::class, 'downloadPenjualan'])->name('penjualan.download');

    Route::get('pembelian/{uuid}', [PublicDocumentController::class, 'invoicePembelian'])->name('pembelian');
    Route::get('pembelian/{uuid}/download', [PublicDocumentController::class, 'downloadPembelian'])->name('pembelian.download');

    Route::get('biaya/{uuid}', [PublicDocumentController::class, 'invoiceBiaya'])->name('biaya');
    Route::get('biaya/{uuid}/download', [PublicDocumentController::class, 'downloadBiaya'])->name('biaya.download');

    Route::get('kunjungan/{uuid}', [PublicDocumentController::class, 'invoiceKunjungan'])->name('kunjungan');
    Route::get('kunjungan/{uuid}/download', [PublicDocumentController::class, 'downloadKunjungan'])->name('kunjungan.download');

    Route::get('pembayaran/{uuid}', [PublicDocumentController::class, 'invoicePembayaran'])->name('pembayaran');
    Route::get('pembayaran/{uuid}/download', [PublicDocumentController::class, 'downloadPembayaran'])->name('pembayaran.download');

    Route::get('penerimaan-barang/{uuid}', [PublicDocumentController::class, 'invoicePenerimaanBarang'])->name('penerimaan');
    Route::get('penerimaan-barang/{uuid}/download', [PublicDocumentController::class, 'downloadPenerimaanBarang'])->name('penerimaan.download');
});

// ========================================================================
// PUBLIC RECEIPT / STRUK (No Auth - UUID based)
// ========================================================================
Route::get('struk/{type}/{uuid}', [PublicDocumentController::class, 'publicStruk'])->name('public.struk.show');
