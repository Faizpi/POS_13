<?php

namespace Tests\Feature;

use App\Mail\TransaksiInvoiceMail;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use App\Services\InvoiceEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_send_invoice_generates_pdf_attachment_and_sends_mail(): void
    {
        Mail::fake();

        $penjualan = $this->makePenjualan();

        $sent = InvoiceEmailService::sendInvoice($penjualan, 'penjualan', 'customer@example.test');

        $this->assertTrue($sent);
        Mail::assertSent(TransaksiInvoiceMail::class, function (TransaksiInvoiceMail $mail): bool {
            return $mail->hasTo('customer@example.test')
                && $mail->type === 'penjualan'
                && str_starts_with($mail->pdfContent, '%PDF');
        });
    }

    public function test_get_approver_emails_returns_super_admin_and_gudang_admins(): void
    {
        $sales = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();

        $emails = InvoiceEmailService::getApproverEmails($sales->gudang_id);

        $this->assertEqualsCanonicalizing([
            'superadmin@hibiscusefsya.com',
            'admin@hibiscusefsya.com',
        ], $emails);
    }

    private function makePenjualan(): Penjualan
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();

        $penjualan = Penjualan::create([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'nomor' => 'INV-MAIL-001',
            'tipe_harga' => 'retail',
            'pelanggan' => 'Toko Melati',
            'no_telepon' => '081111111111',
            'tgl_transaksi' => now()->toDateString(),
            'syarat_pembayaran' => 'Cash',
            'status' => 'Approved',
            'diskon_akhir' => 0,
            'tax_percentage' => 0,
            'grand_total' => 25000,
        ]);

        PenjualanItem::create([
            'penjualan_id' => $penjualan->id,
            'produk_id' => $produk->id,
            'kuantitas' => 1,
            'unit' => 'Pcs',
            'harga_satuan' => 25000,
            'diskon' => 0,
            'diskon_nominal' => 0,
            'jumlah_baris' => 25000,
        ]);

        return $penjualan->refresh();
    }
}
