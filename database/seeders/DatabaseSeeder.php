<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Kontak;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed non-production fixture data for contract testing.
     * Matches the fixture requirements from 07-test-plan.md
     */
    public function run(): void
    {
        // === Gudang ===
        $gudangA = Gudang::create(['nama_gudang' => 'Gudang A', 'alamat_gudang' => 'Jl. Gudang A No.1']);
        $gudangB = Gudang::create(['nama_gudang' => 'Gudang B', 'alamat_gudang' => 'Jl. Gudang B No.2']);

        // === Users ===
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@hibiscusefsya.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'receives_transaction_email' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin Gudang',
            'email' => 'admin@hibiscusefsya.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_gudang_id' => $gudangA->id,
            'receives_transaction_email' => true,
            'can_export_pdf' => true,
            'can_export_excel' => true,
        ]);
        $admin->gudangs()->attach([$gudangA->id, $gudangB->id]);

        $spectator = User::create([
            'name' => 'Spectator',
            'email' => 'spectator@hibiscusefsya.com',
            'password' => Hash::make('password123'),
            'role' => 'spectator',
            'current_gudang_id' => $gudangA->id,
        ]);
        $spectator->spectatorGudangs()->attach([$gudangA->id]);

        $userA = User::create([
            'name' => 'Sales A',
            'email' => 'salesa@hibiscusefsya.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'gudang_id' => $gudangA->id,
            'no_telp' => '081234567890',
        ]);

        $userB = User::create([
            'name' => 'Sales B',
            'email' => 'salesb@hibiscusefsya.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'gudang_id' => $gudangB->id,
        ]);

        // === Produk ===
        $produk1 = Produk::create([
            'nama_produk' => 'Sabun Hibiscus 100ml',
            'item_code' => 'SBN-001',
            'harga' => 25000,
            'harga_grosir' => 22000,
            'satuan' => 'Pcs',
        ]);

        $produk2 = Produk::create([
            'nama_produk' => 'Shampoo Efsya 250ml',
            'item_code' => 'SHP-001',
            'harga' => 45000,
            'harga_grosir' => 40000,
            'satuan' => 'Pcs',
        ]);

        $produk3 = Produk::create([
            'nama_produk' => 'Body Lotion 200ml',
            'item_code' => 'BDL-001',
            'harga' => 35000,
            'harga_grosir' => 30000,
            'satuan' => 'Pcs',
        ]);

        // === Stok Gudang A ===
        GudangProduk::create(['gudang_id' => $gudangA->id, 'produk_id' => $produk1->id, 'stok' => 100, 'stok_penjualan' => 80, 'stok_gratis' => 10, 'stok_sample' => 10]);
        GudangProduk::create(['gudang_id' => $gudangA->id, 'produk_id' => $produk2->id, 'stok' => 50, 'stok_penjualan' => 40, 'stok_gratis' => 5, 'stok_sample' => 5]);
        GudangProduk::create(['gudang_id' => $gudangA->id, 'produk_id' => $produk3->id, 'stok' => 75, 'stok_penjualan' => 60, 'stok_gratis' => 8, 'stok_sample' => 7]);

        // === Stok Gudang B ===
        GudangProduk::create(['gudang_id' => $gudangB->id, 'produk_id' => $produk1->id, 'stok' => 30, 'stok_penjualan' => 25, 'stok_gratis' => 3, 'stok_sample' => 2]);
        GudangProduk::create(['gudang_id' => $gudangB->id, 'produk_id' => $produk2->id, 'stok' => 20, 'stok_penjualan' => 15, 'stok_gratis' => 3, 'stok_sample' => 2]);

        // === Kontak ===
        Kontak::create([
            'kode_kontak' => 'KT00001',
            'nama' => 'Toko Melati',
            'no_telp' => '081111111111',
            'pin' => '123456',
            'alamat' => 'Jl. Melati No.1',
            'diskon_persen' => 5,
            'gudang_id' => $gudangA->id,
            'created_by' => $userA->id,
        ]);

        Kontak::create([
            'kode_kontak' => 'KT00002',
            'nama' => 'Toko Mawar',
            'no_telp' => '082222222222',
            'pin' => '654321',
            'alamat' => 'Jl. Mawar No.2',
            'gudang_id' => $gudangB->id,
            'created_by' => $userB->id,
        ]);
    }
}
