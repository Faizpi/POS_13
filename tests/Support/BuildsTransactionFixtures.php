<?php

namespace Tests\Support;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\KunjunganItem;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\PersonalAccessToken;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait BuildsTransactionFixtures
{
    protected function transactionGudang(array $attributes = []): Gudang
    {
        return Gudang::create(array_merge([
            'nama_gudang' => 'Test Gudang '.Str::random(8),
            'alamat_gudang' => 'Jl. Test Gudang',
        ], $attributes));
    }

    protected function transactionProduk(array $attributes = []): Produk
    {
        return Produk::create(array_merge([
            'nama_produk' => 'Test Produk '.Str::random(8),
            'item_code' => 'TP-'.Str::upper(Str::random(10)),
            'harga' => 25000,
            'harga_grosir' => 22000,
            'satuan' => 'Pcs',
            'deskripsi' => 'Produk fixture untuk transaction integrity tests.',
        ], $attributes));
    }

    protected function transactionStock(Gudang $gudang, Produk $produk, array $attributes = []): GudangProduk
    {
        $values = array_merge([
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ], $attributes);

        $values['stok'] = $values['stok'] ?? (
            (int) $values['stok_penjualan']
            + (int) $values['stok_gratis']
            + (int) $values['stok_sample']
        );

        return GudangProduk::create(array_merge($values, [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
        ]));
    }

    protected function transactionUser(string $role = 'user', ?Gudang $gudang = null, array $attributes = []): User
    {
        $gudang ??= $role === 'super_admin' ? null : $this->transactionGudang();

        $user = User::create(array_merge([
            'name' => 'Test '.Str::title(str_replace('_', ' ', $role)).' '.Str::random(6),
            'email' => Str::lower($role).'.'.Str::uuid().'@example.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'gudang_id' => $role === 'user' ? $gudang?->id : null,
            'current_gudang_id' => in_array($role, ['admin', 'spectator'], true) ? $gudang?->id : null,
            'receives_transaction_email' => false,
            'receives_transaction_whatsapp' => false,
            'can_export_pdf' => $role === 'admin',
            'can_export_excel' => $role === 'admin',
        ], $attributes));

        if ($role === 'admin' && $gudang !== null) {
            $user->gudangs()->syncWithoutDetaching([$gudang->id]);
        }

        if ($role === 'spectator' && $gudang !== null) {
            $user->spectatorGudangs()->syncWithoutDetaching([$gudang->id]);
        }

        return $user;
    }

    protected function apiTokenFor(User $user): string
    {
        $plainToken = Str::random(64);

        PersonalAccessToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainToken;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaderFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->apiTokenFor($user)];
    }

    protected function transactionKontak(?Gudang $gudang = null, ?User $creator = null, array $attributes = []): Kontak
    {
        $gudang ??= $this->transactionGudang();
        $creator ??= $this->transactionUser('user', $gudang);

        return Kontak::create(array_merge([
            'kode_kontak' => 'KT'.Str::upper(Str::random(8)),
            'nama' => 'Test Kontak '.Str::random(8),
            'email' => 'kontak.'.Str::uuid().'@example.test',
            'no_telp' => '0812'.random_int(10000000, 99999999),
            'pin' => '123456',
            'alamat' => 'Jl. Test Kontak',
            'diskon_persen' => 0,
            'gudang_id' => $gudang->id,
            'created_by' => $creator->id,
        ], $attributes));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function transactionPenjualan(?User $user = null, ?Gudang $gudang = null, array $attributes = [], array $items = []): Penjualan
    {
        $gudang ??= $this->transactionGudang();
        $user ??= $this->transactionUser('user', $gudang);

        if ($items === []) {
            $produk = $this->transactionProduk();
            $this->transactionStock($gudang, $produk);
            $items = [['produk' => $produk, 'kuantitas' => 2, 'harga_satuan' => 25000]];
        }

        $grandTotal = $attributes['grand_total'] ?? $this->lineTotalSum($items);

        $penjualan = Penjualan::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'nomor' => 'INV-TEST-'.Str::upper(Str::random(10)),
            'pelanggan' => 'Test Customer',
            'tgl_transaksi' => now()->toDateString(),
            'syarat_pembayaran' => 'Cash',
            'status' => 'Pending',
            'grand_total' => $grandTotal,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'tipe_harga' => 'retail',
            'lampiran_paths' => [],
        ], $attributes));

        foreach ($items as $item) {
            $produk = $item['produk'] ?? $this->transactionProduk();
            $qty = $item['kuantitas'] ?? 1;
            $price = $item['harga_satuan'] ?? $produk->harga;
            $discount = $item['diskon'] ?? 0;
            $lineTotal = $item['jumlah_baris'] ?? (((float) $qty * (float) $price) * (1 - ((float) $discount / 100)));

            PenjualanItem::create([
                'penjualan_id' => $penjualan->id,
                'produk_id' => $produk instanceof Produk ? $produk->id : $item['produk_id'],
                'deskripsi' => $item['deskripsi'] ?? null,
                'kuantitas' => $qty,
                'unit' => $item['unit'] ?? 'Pcs',
                'harga_satuan' => $price,
                'diskon' => $discount,
                'diskon_nominal' => $item['diskon_nominal'] ?? 0,
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => $item['expired_date'] ?? null,
                'jumlah_baris' => $lineTotal,
            ]);
        }

        return $penjualan->refresh()->load('items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function transactionPembelian(?User $user = null, ?Gudang $gudang = null, array $attributes = [], array $items = []): Pembelian
    {
        $gudang ??= $this->transactionGudang();
        $user ??= $this->transactionUser('user', $gudang);

        if ($items === []) {
            $produk = $this->transactionProduk();
            $items = [['produk' => $produk, 'kuantitas' => 2, 'harga_satuan' => 15000]];
        }

        $pembelian = Pembelian::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'kontak_id' => $attributes['kontak_id'] ?? $this->transactionKontak($gudang, $user)->id,
            'nomor' => 'PR-TEST-'.Str::upper(Str::random(10)),
            'tgl_transaksi' => now()->toDateString(),
            'syarat_pembayaran' => 'Net 30',
            'urgensi' => 'Normal',
            'status' => 'Pending',
            'grand_total' => $attributes['grand_total'] ?? $this->lineTotalSum($items),
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'tipe_harga' => 'retail',
            'lampiran_paths' => [],
        ], $attributes));

        foreach ($items as $item) {
            $produk = $item['produk'] ?? $this->transactionProduk();
            $qty = $item['kuantitas'] ?? 1;
            $price = $item['harga_satuan'] ?? $produk->harga;
            $discount = $item['diskon'] ?? 0;

            PembelianItem::create([
                'pembelian_id' => $pembelian->id,
                'produk_id' => $produk instanceof Produk ? $produk->id : $item['produk_id'],
                'deskripsi' => $item['deskripsi'] ?? null,
                'kuantitas' => $qty,
                'unit' => $item['unit'] ?? 'Pcs',
                'harga_satuan' => $price,
                'diskon' => $discount,
                'jumlah_baris' => $item['jumlah_baris'] ?? (((float) $qty * (float) $price) * (1 - ((float) $discount / 100))),
            ]);
        }

        return $pembelian->refresh()->load('items');
    }

    protected function transactionPembayaran(Penjualan $penjualan, ?User $user = null, array $attributes = []): Pembayaran
    {
        $user ??= $penjualan->user;

        return Pembayaran::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $penjualan->gudang_id,
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'nomor' => 'PAY-TEST-'.Str::upper(Str::random(10)),
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => $penjualan->grand_total,
            'status' => 'Pending',
            'lampiran_paths' => [],
        ], $attributes));
    }

    protected function transactionPembayaranHutang(Pembelian $pembelian, ?User $user = null, array $attributes = []): Pembayaran
    {
        $user ??= $pembelian->user;

        return Pembayaran::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $pembelian->gudang_id,
            'type' => 'hutang',
            'pembelian_id' => $pembelian->id,
            'nomor' => 'BAYH-TEST-'.Str::upper(Str::random(10)),
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => $pembelian->grand_total,
            'status' => 'Pending',
            'lampiran_paths' => [],
        ], $attributes));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function transactionPenerimaanBarang(?Pembelian $pembelian = null, ?User $user = null, ?Gudang $gudang = null, array $attributes = [], array $items = []): PenerimaanBarang
    {
        $gudang ??= $this->transactionGudang();
        $user ??= $this->transactionUser('user', $gudang);
        $pembelian ??= $this->transactionPembelian($user, $gudang);

        if ($items === []) {
            $produk = $pembelian->items()->first()?->produk ?? $this->transactionProduk();
            $items = [['produk' => $produk, 'qty_diterima' => 2, 'tipe_stok' => 'penjualan']];
        }

        $penerimaan = PenerimaanBarang::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'pembelian_id' => $pembelian->id,
            'nomor' => 'RCV-TEST-'.Str::upper(Str::random(10)),
            'tgl_penerimaan' => now()->toDateString(),
            'no_surat_jalan' => 'SJ-'.Str::upper(Str::random(8)),
            'status' => 'Pending',
            'lampiran_paths' => [],
        ], $attributes));

        foreach ($items as $item) {
            $produk = $item['produk'] ?? $this->transactionProduk();

            PenerimaanBarangItem::create([
                'penerimaan_barang_id' => $penerimaan->id,
                'produk_id' => $produk instanceof Produk ? $produk->id : $item['produk_id'],
                'qty_diterima' => $item['qty_diterima'] ?? 1,
                'qty_reject' => $item['qty_reject'] ?? 0,
                'tipe_stok' => $item['tipe_stok'] ?? 'penjualan',
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => $item['expired_date'] ?? null,
                'keterangan' => $item['keterangan'] ?? null,
            ]);
        }

        return $penerimaan->refresh()->load('items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function transactionKunjungan(?User $user = null, ?Gudang $gudang = null, ?Kontak $kontak = null, array $attributes = [], array $items = []): Kunjungan
    {
        $gudang ??= $this->transactionGudang();
        $user ??= $this->transactionUser('user', $gudang);
        $kontak ??= $this->transactionKontak($gudang, $user);

        if ($items === [] && in_array($attributes['tujuan'] ?? 'Promo Gratis', ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample'], true)) {
            $produk = $this->transactionProduk();
            $this->transactionStock($gudang, $produk);
            $items = [['produk' => $produk, 'jumlah' => 1]];
        }

        $kunjungan = Kunjungan::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'kontak_id' => $kontak->id,
            'nomor' => 'VST-TEST-'.Str::upper(Str::random(10)),
            'sales_nama' => $user->name,
            'sales_no_telepon' => $user->no_telp,
            'sales_alamat' => $user->alamat,
            'tgl_kunjungan' => now()->toDateString(),
            'tujuan' => 'Promo Gratis',
            'status' => 'Pending',
            'lampiran_paths' => [],
        ], $attributes));

        foreach ($items as $item) {
            $produk = $item['produk'] ?? $this->transactionProduk();

            KunjunganItem::create([
                'kunjungan_id' => $kunjungan->id,
                'produk_id' => $produk instanceof Produk ? $produk->id : $item['produk_id'],
                'jumlah' => $item['jumlah'] ?? $item['kuantitas'] ?? 1,
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => $item['expired_date'] ?? null,
                'keterangan' => $item['keterangan'] ?? null,
            ]);
        }

        return $kunjungan->refresh()->load('items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function lineTotalSum(array $items): float
    {
        return round(array_sum(array_map(function (array $item): float {
            $produk = $item['produk'] ?? null;
            $qty = (float) ($item['kuantitas'] ?? $item['qty'] ?? 1);
            $price = (float) ($item['harga_satuan'] ?? ($produk instanceof Produk ? $produk->harga : 0));
            $discount = (float) ($item['diskon'] ?? 0);

            return (float) ($item['jumlah_baris'] ?? (($qty * $price) * (1 - ($discount / 100))));
        }, $items)), 2);
    }
}
