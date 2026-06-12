<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocController extends Controller
{
    /**
     * Tampilkan halaman dokumentasi API.
     */
    public function index()
    {
        $apiUrl = url('api/v1');
        return view('api-docs.index', compact('apiUrl'));
    }

    public function json()
    {
        return response()->json($this->buildApiSpec());
    }

    public function download()
    {
        $spec = json_encode($this->buildApiSpec(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return response($spec, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="hibiscusefsya-api-spec.json"',
        ]);
    }

    public function downloadPostman()
    {
        $postman = $this->buildPostmanCollection();
        $json = json_encode($postman, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="hibiscusefsya-postman.json"',
        ]);
    }

    private function buildApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title'   => 'Hibiscus Efsya POS API',
                'version' => '1.0.0',
                'description' => 'API untuk aplikasi mobile Hibiscus Efsya POS. Semua endpoint prefix /api/v1.',
            ],
            'servers' => [['url' => url('api/v1')]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'SHA256-hashed-64char-random-token',
                    ],
                ],
            ],
            'security' => [['bearerAuth' => []]],
            'paths' => $this->buildPaths(),
        ];
    }

    private function buildPaths(): array
    {
        return [
            '/login'  => ['post' => ['summary' => 'Login dan dapatkan token', 'security' => [], 'requestBody' => ['content' => ['application/json' => ['schema' => ['properties' => ['email' => ['type' => 'string'], 'password' => ['type' => 'string'], 'device_name' => ['type' => 'string']]]]]], 'responses' => ['200' => ['description' => 'Login berhasil'], '401' => ['description' => 'Email atau password salah.']]]],
            '/logout' => ['post' => ['summary' => 'Logout dan revoke token', 'responses' => ['200' => ['description' => 'Logout berhasil.']]]],
            '/profile' => ['get' => ['summary' => 'Get profile user'], 'put' => ['summary' => 'Update profile']],
            '/dashboard' => ['get' => ['summary' => 'Dashboard metrics per role']],
            '/gudang' => ['get' => ['summary' => 'List gudang sesuai role']],
            '/gudang/switch' => ['post' => ['summary' => 'Switch gudang aktif']],
            '/produk' => ['get' => ['summary' => 'List produk'], 'post' => ['summary' => 'Buat produk baru (super_admin)']],
            '/kontak' => ['get' => ['summary' => 'List kontak'], 'post' => ['summary' => 'Buat kontak baru']],
            '/penjualan' => ['get' => ['summary' => 'List penjualan'], 'post' => ['summary' => 'Buat penjualan']],
            '/penjualan/{id}/approve'   => ['post' => ['summary' => 'Approve penjualan']],
            '/penjualan/{id}/cancel'    => ['post' => ['summary' => 'Cancel penjualan']],
            '/penjualan/{id}/mark-paid' => ['post' => ['summary' => 'Mark penjualan Lunas']],
            '/pembelian' => ['get' => ['summary' => 'List pembelian'], 'post' => ['summary' => 'Buat pembelian']],
            '/biaya'     => ['get' => ['summary' => 'List biaya'], 'post' => ['summary' => 'Buat biaya']],
            '/kunjungan' => ['get' => ['summary' => 'List kunjungan'], 'post' => ['summary' => 'Buat kunjungan']],
            '/pembayaran' => ['get' => ['summary' => 'List pembayaran'], 'post' => ['summary' => 'Buat pembayaran']],
            '/penerimaan-barang' => ['get' => ['summary' => 'List penerimaan barang'], 'post' => ['summary' => 'Buat penerimaan']],
            '/stok'     => ['get' => ['summary' => 'Stok per gudang'], 'post' => ['summary' => 'Update stok manual (super_admin)']],
            '/stok/log' => ['get' => ['summary' => 'Riwayat perubahan stok']],
            '/users'    => ['get' => ['summary' => 'List users (super_admin)'], 'post' => ['summary' => 'Buat user (super_admin)']],
            '/print/{type}/{id}/qr'        => ['get' => ['summary' => 'QR data untuk semua tipe transaksi']],
            '/print/{type}/{id}/bluetooth' => ['get' => ['summary' => 'Bluetooth print JSON (penjualan/pembelian/biaya/kunjungan)']],
        ];
    }

    private function buildPostmanCollection(): array
    {
        return [
            'info' => [
                'name'   => 'Hibiscus Efsya POS API v1',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [
                [
                    'name'    => 'Auth',
                    'item'    => [
                        ['name' => 'Login', 'request' => ['method' => 'POST', 'header' => [], 'url' => ['raw' => '{{base_url}}/api/v1/login'], 'body' => ['mode' => 'raw', 'raw' => json_encode(['email' => 'admin@example.com', 'password' => 'password123'])]]],
                        ['name' => 'Logout', 'request' => ['method' => 'POST', 'header' => [['key' => 'Authorization', 'value' => 'Bearer {{token}}']], 'url' => ['raw' => '{{base_url}}/api/v1/logout']]],
                        ['name' => 'Profile', 'request' => ['method' => 'GET', 'header' => [['key' => 'Authorization', 'value' => 'Bearer {{token}}']], 'url' => ['raw' => '{{base_url}}/api/v1/profile']]],
                    ],
                ],
                ['name' => 'Penjualan', 'item' => [
                    ['name' => 'List', 'request' => ['method' => 'GET', 'header' => [['key' => 'Authorization', 'value' => 'Bearer {{token}}']], 'url' => ['raw' => '{{base_url}}/api/v1/penjualan']]],
                ]],
            ],
            'variable' => [
                ['key' => 'base_url', 'value' => url('')],
                ['key' => 'token', 'value' => ''],
            ],
        ];
    }
}
