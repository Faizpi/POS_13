<?php

namespace App\Http\Controllers;

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
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="hibiscusefsya-api-spec.json"',
        ]);
    }

    public function downloadPostman()
    {
        $postman = $this->buildPostmanCollection();
        $json = json_encode($postman, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="hibiscusefsya-postman.json"',
        ]);
    }

    private function buildApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Hibiscus Efsya POS API',
                'version' => '1.0.0',
                'description' => 'API untuk aplikasi mobile Hibiscus Efsya POS. Semua endpoint prefix /api/v1.',
            ],
            'servers' => [['url' => url('api/v1')]],
            'tags' => [
                ['name' => 'Authentication', 'description' => 'Login, logout, profile, password management'],
                ['name' => 'Dashboard', 'description' => 'Dashboard metrics, daily report, export'],
                ['name' => 'Penjualan', 'description' => 'Sales transactions CRUD + approve/cancel/mark-paid'],
                ['name' => 'Pembelian', 'description' => 'Purchase transactions CRUD + approve/cancel'],
                ['name' => 'Biaya', 'description' => 'Expense transactions CRUD + approve/cancel'],
                ['name' => 'Kunjungan', 'description' => 'Visit transactions CRUD + approve/cancel'],
                ['name' => 'Pembayaran', 'description' => 'Receivable payments (piutang) CRUD + approve/cancel'],
                ['name' => 'Pembayaran Hutang', 'description' => 'Payable payments'],
                ['name' => 'Penerimaan Barang', 'description' => 'Goods receipt'],
                ['name' => 'Gudang', 'description' => 'Warehouse management + stock'],
                ['name' => 'Produk', 'description' => 'Product master data'],
                ['name' => 'Kontak', 'description' => 'Contact/customer master data'],
                ['name' => 'Stok', 'description' => 'Stock management + log'],
                ['name' => 'Stock Opname', 'description' => 'Stock opname/audit'],
                ['name' => 'Neraca', 'description' => 'Balance sheet reports'],
                ['name' => 'Piutang', 'description' => 'Receivable reports'],
                ['name' => 'Hutang', 'description' => 'Payable reports'],
                ['name' => 'Catatan Hutang', 'description' => 'Debt notes'],
                ['name' => 'Tutup Buku', 'description' => 'Period closing'],
                ['name' => 'User Management', 'description' => 'User CRUD'],
                ['name' => 'Print & QR', 'description' => 'QR data and bluetooth print'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'SHA256-hashed-64char-random-token',
                    ],
                ],
                'schemas' => $this->buildSchemas(),
            ],
            'security' => [['bearerAuth' => []]],
            'paths' => $this->buildPaths(),
        ];
    }

    private function buildPaths(): array
    {
        $ref = static fn (string $schema): array => ['$ref' => "#/components/schemas/{$schema}"];
        $jsonContent = static fn (array $schema): array => ['content' => ['application/json' => ['schema' => $schema]]];
        $requestBody = static fn (array $schema): array => ['required' => true] + $jsonContent($schema);
        $response = static fn (string $description, ?array $schema = null): array => $schema === null
            ? ['description' => $description]
            : ['description' => $description] + $jsonContent($schema);
        $parameter = static fn (string $name, string $type = 'integer', ?string $description = null): array => [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'description' => $description ?? ucfirst($name),
            'schema' => ['type' => $type],
        ];
        $operation = static function (
            string $tag,
            string $summary,
            string $operationId,
            array $responses,
            array $parameters = [],
            ?array $requestBody = null,
            ?array $security = null,
        ): array {
            $operation = [
                'tags' => [$tag],
                'summary' => $summary,
                'operationId' => $operationId,
                'responses' => $responses,
            ];

            if ($parameters !== []) {
                $operation['parameters'] = $parameters;
            }

            if ($requestBody !== null) {
                $operation['requestBody'] = $requestBody;
            }

            if ($security !== null) {
                $operation['security'] = $security;
            }

            return $operation;
        };

        $id = [$parameter('id')];
        $gudangId = [$parameter('gudangId')];
        $printParameters = [$parameter('type', 'string', 'Transaction type'), $parameter('id')];
        $success = $ref('SuccessResponse');
        $paginated = $ref('PaginatedResponse');
        $error = $ref('ErrorResponse');
        $validation = $ref('ValidationError');
        $transaction = $ref('Penjualan');
        $notFound = ['404' => $response('Data tidak ditemukan.', $error)];
        $validationError = ['422' => $response('Validasi gagal.', $validation)];
        $ok = ['200' => $response('Berhasil.', $success)];
        $deleted = ['200' => $response('Data berhasil dihapus.', $success)] + $notFound;
        $file = ['200' => $response('File berhasil dibuat atau diunduh.')];

        return [
            '/login' => [
                'post' => $operation('Authentication', 'Login dan dapatkan bearer token', 'login', [
                    '200' => $response('Login berhasil.', $ref('LoginResponse')),
                    '401' => $response('Email atau password salah.', $error),
                    '422' => $response('Validasi gagal.', $validation),
                ], [], $requestBody($ref('LoginRequest')), []),
            ],
            '/logout' => ['post' => $operation('Authentication', 'Logout dan revoke token aktif', 'logout', $ok)],
            '/profile' => [
                'get' => $operation('Authentication', 'Ambil profil user aktif', 'getProfile', ['200' => $response('Profil user.', $ref('User'))]),
                'put' => $operation('Authentication', 'Update profil user aktif', 'updateProfile', $ok + $validationError, [], $requestBody($ref('User'))),
            ],
            '/change-password' => ['post' => $operation('Authentication', 'Ubah password user aktif', 'changePassword', $ok + $validationError, [], $requestBody(['type' => 'object', 'required' => ['current_password', 'password', 'password_confirmation'], 'properties' => ['current_password' => ['type' => 'string'], 'password' => ['type' => 'string'], 'password_confirmation' => ['type' => 'string']]]))],
            '/profile/avatar' => [
                'post' => $operation('Authentication', 'Upload avatar profil user aktif', 'uploadProfileAvatar', $ok + $validationError),
                'delete' => $operation('Authentication', 'Hapus avatar profil user aktif', 'deleteProfileAvatar', $ok),
            ],

            '/dashboard' => ['get' => $operation('Dashboard', 'Ambil metrik dashboard sesuai role', 'getDashboard', ['200' => $response('Metrik dashboard.', $success)])],
            '/dashboard/daily-report' => ['get' => $operation('Dashboard', 'Ambil laporan harian dashboard', 'getDashboardDailyReport', ['200' => $response('Laporan harian.', $success)])],
            '/dashboard/daily-report/pdf' => ['get' => $operation('Dashboard', 'Unduh laporan harian PDF', 'downloadDashboardDailyReportPdf', $file)],
            '/dashboard/export/options' => ['get' => $operation('Dashboard', 'Ambil opsi filter dan tipe transaksi untuk export dashboard', 'getDashboardExportOptions', ['200' => $response('Opsi export termasuk transaction_types (all, penjualan, pembelian, biaya, kunjungan, pembayaran_piutang, pembayaran_hutang), status_filters, gudangs, sales_users, biaya_jenis_filters, tujuan_kunjungan_filters.', $ref('ExportOptionsResponse'))])],
            '/dashboard/export' => ['post' => $operation('Dashboard', 'Export transaksi ke PDF atau Excel. Tipe pembayaran dipisah: pembayaran_piutang dan pembayaran_hutang. Dalam laporan all, baris pembayaran dilabeli Pembayaran Piutang atau Pembayaran Hutang.', 'exportDashboard', $file + $validationError, [], $requestBody($ref('ExportRequest')))],
            '/lampiran/download' => ['get' => $operation('Dashboard', 'Unduh lampiran laporan', 'downloadLampiran', $file)],

            '/print/{type}/{id}/qr' => ['get' => $operation('Print & QR', 'Ambil data QR untuk transaksi', 'getPrintQrData', ['200' => $response('Data QR.', $success)] + $notFound, $printParameters)],
            '/print/{type}/{id}/bluetooth' => ['get' => $operation('Print & QR', 'Ambil JSON bluetooth print transaksi', 'getBluetoothPrintData', ['200' => $response('Data print bluetooth.', $success)] + $notFound, $printParameters)],

            '/gudang' => [
                'get' => $operation('Gudang', 'List gudang sesuai role', 'listGudang', ['200' => $response('Daftar gudang.', $paginated)]),
                'post' => $operation('Gudang', 'Buat gudang baru', 'createGudang', ['201' => $response('Gudang dibuat.', $ref('Gudang'))] + $validationError, [], $requestBody($ref('Gudang'))),
            ],
            '/gudang/{id}' => [
                'put' => $operation('Gudang', 'Update gudang', 'updateGudang', ['200' => $response('Gudang diperbarui.', $ref('Gudang'))] + $notFound + $validationError, $id, $requestBody($ref('Gudang'))),
                'delete' => $operation('Gudang', 'Hapus gudang', 'deleteGudang', $deleted, $id),
            ],
            '/gudang/switch' => ['post' => $operation('Gudang', 'Switch gudang aktif', 'switchGudang', $ok + $validationError, [], $requestBody(['type' => 'object', 'required' => ['gudang_id'], 'properties' => ['gudang_id' => ['type' => 'integer']]]))],
            '/gudang/stok' => ['get' => $operation('Gudang', 'List stok gudang', 'listGudangStok', ['200' => $response('Daftar stok gudang.', $paginated)])],
            '/gudang/stok-log' => ['get' => $operation('Gudang', 'Riwayat stok gudang', 'listGudangStokLog', ['200' => $response('Riwayat stok gudang.', $paginated)])],
            '/gudang/stok/export' => ['get' => $operation('Gudang', 'Export stok gudang', 'exportGudangStok', $file)],

            '/produk' => [
                'get' => $operation('Produk', 'List produk', 'listProduk', ['200' => $response('Daftar produk.', $paginated)]),
                'post' => $operation('Produk', 'Buat produk baru', 'createProduk', ['201' => $response('Produk dibuat.', $ref('Produk'))] + $validationError, [], $requestBody($ref('Produk'))),
            ],
            '/produk/{id}' => [
                'get' => $operation('Produk', 'Detail produk', 'getProduk', ['200' => $response('Detail produk.', $ref('Produk'))] + $notFound, $id),
                'put' => $operation('Produk', 'Update produk', 'updateProduk', ['200' => $response('Produk diperbarui.', $ref('Produk'))] + $notFound + $validationError, $id, $requestBody($ref('Produk'))),
                'delete' => $operation('Produk', 'Hapus produk', 'deleteProduk', $deleted, $id),
            ],
            '/produk/stok/{gudangId}' => ['get' => $operation('Produk', 'List stok produk per gudang', 'listProdukStokByGudang', ['200' => $response('Stok produk per gudang.', $paginated)], $gudangId)],

            '/kontak' => [
                'get' => $operation('Kontak', 'List kontak', 'listKontak', ['200' => $response('Daftar kontak.', $paginated)]),
                'post' => $operation('Kontak', 'Buat kontak baru', 'createKontak', ['201' => $response('Kontak dibuat.', $ref('Kontak'))] + $validationError, [], $requestBody($ref('Kontak'))),
            ],
            '/kontak/{id}' => [
                'get' => $operation('Kontak', 'Detail kontak', 'getKontak', ['200' => $response('Detail kontak.', $ref('Kontak'))] + $notFound, $id),
                'put' => $operation('Kontak', 'Update kontak', 'updateKontak', ['200' => $response('Kontak diperbarui.', $ref('Kontak'))] + $notFound + $validationError, $id, $requestBody($ref('Kontak'))),
                'delete' => $operation('Kontak', 'Hapus kontak', 'deleteKontak', $deleted, $id),
            ],

            '/penjualan' => [
                'get' => $operation('Penjualan', 'List penjualan', 'listPenjualan', ['200' => $response('Daftar penjualan.', $paginated)]),
                'post' => $operation('Penjualan', 'Buat penjualan baru', 'createPenjualan', ['201' => $response('Penjualan dibuat.', $transaction)] + $validationError, [], $requestBody($transaction)),
            ],
            '/penjualan/{id}' => [
                'get' => $operation('Penjualan', 'Detail penjualan', 'getPenjualan', ['200' => $response('Detail penjualan.', $transaction)] + $notFound, $id),
                'put' => $operation('Penjualan', 'Update penjualan', 'updatePenjualan', ['200' => $response('Penjualan diperbarui.', $transaction)] + $notFound + $validationError, $id, $requestBody($transaction)),
            ],
            '/penjualan/{id}/approve' => ['post' => $operation('Penjualan', 'Approve penjualan', 'approvePenjualan', $ok + $notFound, $id)],
            '/penjualan/{id}/cancel' => ['post' => $operation('Penjualan', 'Cancel penjualan', 'cancelPenjualan', $ok + $notFound, $id)],
            '/penjualan/{id}/uncancel' => ['post' => $operation('Penjualan', 'Batalkan status cancel penjualan', 'uncancelPenjualan', $ok + $notFound, $id)],
            '/penjualan/{id}/mark-paid' => ['post' => $operation('Penjualan', 'Tandai penjualan lunas', 'markPenjualanPaid', $ok + $notFound, $id)],
            '/penjualan/{id}/unmark-paid' => ['post' => $operation('Penjualan', 'Batalkan status lunas penjualan', 'unmarkPenjualanPaid', $ok + $notFound, $id)],

            '/pembelian' => [
                'get' => $operation('Pembelian', 'List pembelian', 'listPembelian', ['200' => $response('Daftar pembelian.', $paginated)]),
                'post' => $operation('Pembelian', 'Buat pembelian baru', 'createPembelian', ['201' => $response('Pembelian dibuat.', $transaction)] + $validationError, [], $requestBody($transaction)),
            ],
            '/pembelian/{id}' => [
                'get' => $operation('Pembelian', 'Detail pembelian', 'getPembelian', ['200' => $response('Detail pembelian.', $transaction)] + $notFound, $id),
                'put' => $operation('Pembelian', 'Update pembelian', 'updatePembelian', ['200' => $response('Pembelian diperbarui.', $transaction)] + $notFound + $validationError, $id, $requestBody($transaction)),
            ],
            '/pembelian/{id}/approve' => ['post' => $operation('Pembelian', 'Approve pembelian', 'approvePembelian', $ok + $notFound, $id)],
            '/pembelian/{id}/cancel' => ['post' => $operation('Pembelian', 'Cancel pembelian', 'cancelPembelian', $ok + $notFound, $id)],
            '/pembelian/{id}/uncancel' => ['post' => $operation('Pembelian', 'Batalkan status cancel pembelian', 'uncancelPembelian', $ok + $notFound, $id)],

            '/biaya' => [
                'get' => $operation('Biaya', 'List biaya', 'listBiaya', ['200' => $response('Daftar biaya.', $paginated)]),
                'post' => $operation('Biaya', 'Buat biaya baru', 'createBiaya', ['201' => $response('Biaya dibuat.', $success)] + $validationError),
            ],
            '/biaya/{id}' => [
                'get' => $operation('Biaya', 'Detail biaya', 'getBiaya', ['200' => $response('Detail biaya.', $success)] + $notFound, $id),
                'put' => $operation('Biaya', 'Update biaya', 'updateBiaya', ['200' => $response('Biaya diperbarui.', $success)] + $notFound + $validationError, $id),
            ],
            '/biaya/{id}/approve' => ['post' => $operation('Biaya', 'Approve biaya', 'approveBiaya', $ok + $notFound, $id)],
            '/biaya/{id}/cancel' => ['post' => $operation('Biaya', 'Cancel biaya', 'cancelBiaya', $ok + $notFound, $id)],
            '/biaya/{id}/uncancel' => ['post' => $operation('Biaya', 'Batalkan status cancel biaya', 'uncancelBiaya', $ok + $notFound, $id)],

            '/kunjungan' => [
                'get' => $operation('Kunjungan', 'List kunjungan', 'listKunjungan', ['200' => $response('Daftar kunjungan.', $paginated)]),
                'post' => $operation('Kunjungan', 'Buat kunjungan baru', 'createKunjungan', ['201' => $response('Kunjungan dibuat.', $success)] + $validationError),
            ],
            '/kunjungan/{id}' => [
                'get' => $operation('Kunjungan', 'Detail kunjungan', 'getKunjungan', ['200' => $response('Detail kunjungan.', $success)] + $notFound, $id),
                'put' => $operation('Kunjungan', 'Update kunjungan', 'updateKunjungan', ['200' => $response('Kunjungan diperbarui.', $success)] + $notFound + $validationError, $id),
            ],
            '/kunjungan/{id}/approve' => ['post' => $operation('Kunjungan', 'Approve kunjungan', 'approveKunjungan', $ok + $notFound, $id)],
            '/kunjungan/{id}/cancel' => ['post' => $operation('Kunjungan', 'Cancel kunjungan', 'cancelKunjungan', $ok + $notFound, $id)],
            '/kunjungan/{id}/uncancel' => ['post' => $operation('Kunjungan', 'Batalkan status cancel kunjungan', 'uncancelKunjungan', $ok + $notFound, $id)],

            '/pembayaran' => [
                'get' => $operation('Pembayaran', 'List pembayaran piutang', 'listPembayaran', ['200' => $response('Daftar pembayaran.', $paginated)]),
                'post' => $operation('Pembayaran', 'Buat pembayaran piutang baru', 'createPembayaran', ['201' => $response('Pembayaran dibuat.', $success)] + $validationError),
            ],
            '/pembayaran/{id}' => ['get' => $operation('Pembayaran', 'Detail pembayaran piutang', 'getPembayaran', ['200' => $response('Detail pembayaran.', $success)] + $notFound, $id)],
            '/pembayaran/{id}/approve' => ['post' => $operation('Pembayaran', 'Approve pembayaran piutang', 'approvePembayaran', $ok + $notFound, $id)],
            '/pembayaran/{id}/cancel' => ['post' => $operation('Pembayaran', 'Cancel pembayaran piutang', 'cancelPembayaran', $ok + $notFound, $id)],
            '/pembayaran/{id}/uncancel' => ['post' => $operation('Pembayaran', 'Batalkan status cancel pembayaran piutang', 'uncancelPembayaran', $ok + $notFound, $id)],
            '/pembayaran/export-harian-pdf' => ['get' => $operation('Pembayaran', 'Export pembayaran harian PDF', 'exportPembayaranHarianPdf', $file)],
            '/pembayaran/penjualan-by-gudang/{gudangId}' => ['get' => $operation('Pembayaran', 'List penjualan piutang per gudang', 'listPembayaranPenjualanByGudang', ['200' => $response('Daftar penjualan per gudang.', $paginated)], $gudangId)],
            '/pembayaran/penjualan-detail/{id}' => ['get' => $operation('Pembayaran', 'Detail penjualan untuk pembayaran', 'getPembayaranPenjualanDetail', ['200' => $response('Detail penjualan.', $transaction)] + $notFound, $id)],

            '/pembayaran-hutang' => [
                'get' => $operation('Pembayaran Hutang', 'List pembayaran hutang', 'listPembayaranHutang', ['200' => $response('Daftar pembayaran hutang.', $paginated)]),
                'post' => $operation('Pembayaran Hutang', 'Buat pembayaran hutang baru', 'createPembayaranHutang', ['201' => $response('Pembayaran hutang dibuat.', $success)] + $validationError),
            ],
            '/pembayaran-hutang/pembelian-by-gudang/{gudangId}' => ['get' => $operation('Pembayaran Hutang', 'List pembelian hutang per gudang', 'listPembayaranHutangPembelianByGudang', ['200' => $response('Daftar pembelian per gudang.', $paginated)], $gudangId)],
            '/pembayaran-hutang/pembelian-detail/{id}' => ['get' => $operation('Pembayaran Hutang', 'Detail pembelian untuk pembayaran hutang', 'getPembayaranHutangPembelianDetail', ['200' => $response('Detail pembelian.', $transaction)] + $notFound, $id)],

            '/penerimaan-barang' => [
                'get' => $operation('Penerimaan Barang', 'List penerimaan barang', 'listPenerimaanBarang', ['200' => $response('Daftar penerimaan barang.', $paginated)]),
                'post' => $operation('Penerimaan Barang', 'Buat penerimaan barang baru', 'createPenerimaanBarang', ['201' => $response('Penerimaan barang dibuat.', $success)] + $validationError),
            ],
            '/penerimaan-barang/{id}' => ['get' => $operation('Penerimaan Barang', 'Detail penerimaan barang', 'getPenerimaanBarang', ['200' => $response('Detail penerimaan barang.', $success)] + $notFound, $id)],
            '/penerimaan-barang/{id}/approve' => ['post' => $operation('Penerimaan Barang', 'Approve penerimaan barang', 'approvePenerimaanBarang', $ok + $notFound, $id)],
            '/penerimaan-barang/{id}/cancel' => ['post' => $operation('Penerimaan Barang', 'Cancel penerimaan barang', 'cancelPenerimaanBarang', $ok + $notFound, $id)],
            '/penerimaan-barang/{id}/uncancel' => ['post' => $operation('Penerimaan Barang', 'Batalkan status cancel penerimaan barang', 'uncancelPenerimaanBarang', $ok + $notFound, $id)],
            '/penerimaan-barang/pembelian-by-gudang/{gudangId}' => ['get' => $operation('Penerimaan Barang', 'List pembelian untuk penerimaan per gudang', 'listPenerimaanBarangPembelianByGudang', ['200' => $response('Daftar pembelian per gudang.', $paginated)], $gudangId)],
            '/penerimaan-barang/pembelian-detail/{id}' => ['get' => $operation('Penerimaan Barang', 'Detail pembelian untuk penerimaan barang', 'getPenerimaanBarangPembelianDetail', ['200' => $response('Detail pembelian.', $transaction)] + $notFound, $id)],

            '/stok' => [
                'get' => $operation('Stok', 'List stok', 'listStok', ['200' => $response('Daftar stok.', $paginated)]),
                'post' => $operation('Stok', 'Update stok manual', 'updateStokManual', $ok + $validationError),
            ],
            '/stok/log' => ['get' => $operation('Stok', 'Riwayat perubahan stok', 'listStokLog', ['200' => $response('Riwayat stok.', $paginated)])],

            '/stock-opname' => [
                'get' => $operation('Stock Opname', 'List stock opname', 'listStockOpname', ['200' => $response('Daftar stock opname.', $paginated)]),
                'post' => $operation('Stock Opname', 'Buat stock opname baru', 'createStockOpname', ['201' => $response('Stock opname dibuat.', $success)] + $validationError),
            ],
            '/stock-opname/{id}' => ['get' => $operation('Stock Opname', 'Detail stock opname', 'getStockOpname', ['200' => $response('Detail stock opname.', $success)] + $notFound, $id)],
            '/stock-opname/{id}/submit' => ['post' => $operation('Stock Opname', 'Submit stock opname', 'submitStockOpname', $ok + $notFound, $id)],
            '/stock-opname/{id}/apply' => ['post' => $operation('Stock Opname', 'Apply stock opname ke stok', 'applyStockOpname', $ok + $notFound, $id)],

            '/neraca' => ['get' => $operation('Neraca', 'Ambil laporan neraca', 'getNeraca', ['200' => $response('Laporan neraca.', $success)])],
            '/neraca/export-pdf' => ['get' => $operation('Neraca', 'Export neraca PDF', 'exportNeracaPdf', $file)],
            '/neraca/export-excel' => ['get' => $operation('Neraca', 'Export neraca Excel', 'exportNeracaExcel', $file)],
            '/piutang' => ['get' => $operation('Piutang', 'Ambil laporan piutang', 'getPiutang', ['200' => $response('Laporan piutang.', $paginated)])],
            '/piutang/export-pdf' => ['get' => $operation('Piutang', 'Export piutang PDF', 'exportPiutangPdf', $file)],
            '/hutang' => ['get' => $operation('Hutang', 'Ambil laporan hutang', 'getHutang', ['200' => $response('Laporan hutang.', $paginated)])],
            '/hutang/export-pdf' => ['get' => $operation('Hutang', 'Export hutang PDF', 'exportHutangPdf', $file)],
            '/catatan-hutang' => ['get' => $operation('Catatan Hutang', 'List catatan hutang', 'listCatatanHutang', ['200' => $response('Daftar catatan hutang.', $paginated)])],

            '/tutup-buku' => ['get' => $operation('Tutup Buku', 'Ambil status tutup buku', 'getTutupBuku', ['200' => $response('Status tutup buku.', $success)])],
            '/tutup-buku/execute' => ['post' => $operation('Tutup Buku', 'Eksekusi tutup buku periode aktif', 'executeTutupBuku', $ok + $validationError)],
            '/tutup-buku/backup-db' => ['get' => $operation('Tutup Buku', 'Unduh backup database', 'downloadTutupBukuBackupDb', $file)],
            '/tutup-buku/export-data' => ['get' => $operation('Tutup Buku', 'Export data tutup buku', 'exportTutupBukuData', $file)],

            '/users' => [
                'get' => $operation('User Management', 'List users', 'listUsers', ['200' => $response('Daftar user.', $paginated)]),
                'post' => $operation('User Management', 'Buat user baru', 'createUser', ['201' => $response('User dibuat.', $ref('User'))] + $validationError, [], $requestBody($ref('User'))),
            ],
            '/users/{id}' => [
                'get' => $operation('User Management', 'Detail user', 'getUser', ['200' => $response('Detail user.', $ref('User'))] + $notFound, $id),
                'put' => $operation('User Management', 'Update user', 'updateUser', ['200' => $response('User diperbarui.', $ref('User'))] + $notFound + $validationError, $id, $requestBody($ref('User'))),
                'delete' => $operation('User Management', 'Hapus user', 'deleteUser', $deleted, $id),
            ],
        ];
    }

    private function buildSchemas(): array
    {
        return [
            'LoginRequest' => [
                'type' => 'object',
                'required' => ['email', 'password', 'device_name'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password123'],
                    'device_name' => ['type' => 'string', 'example' => 'iPhone 15 Pro'],
                ],
            ],
            'LoginResponse' => [
                'type' => 'object',
                'properties' => [
                    'token' => ['type' => 'string', 'description' => 'Bearer token untuk autentikasi'],
                    'user' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'example' => 'Admin'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'role' => ['type' => 'string', 'enum' => ['super_admin', 'admin_gudang', 'sales'], 'example' => 'super_admin'],
                    'gudang_id' => ['type' => 'integer', 'nullable' => true],
                    'avatar' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Produk' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'nama' => ['type' => 'string', 'example' => 'Madu Hutan 500ml'],
                    'kode' => ['type' => 'string', 'example' => 'MDH-001'],
                    'satuan' => ['type' => 'string', 'example' => 'botol'],
                    'harga_jual' => ['type' => 'number', 'format' => 'double', 'example' => 85000],
                    'harga_beli' => ['type' => 'number', 'format' => 'double', 'example' => 65000],
                    'stok_minimum' => ['type' => 'integer', 'example' => 10],
                    'deskripsi' => ['type' => 'string', 'nullable' => true],
                    'foto' => ['type' => 'string', 'nullable' => true],
                    'aktif' => ['type' => 'boolean', 'example' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Kontak' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'nama' => ['type' => 'string', 'example' => 'Toko Makmur'],
                    'no_telp' => ['type' => 'string', 'nullable' => true, 'example' => '08123456789'],
                    'alamat' => ['type' => 'string', 'nullable' => true],
                    'tipe' => ['type' => 'string', 'enum' => ['customer', 'supplier'], 'example' => 'customer'],
                    'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'catatan' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Gudang' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'nama' => ['type' => 'string', 'example' => 'Gudang Utama'],
                    'alamat' => ['type' => 'string', 'nullable' => true],
                    'keterangan' => ['type' => 'string', 'nullable' => true],
                    'aktif' => ['type' => 'boolean', 'example' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Penjualan' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'nomor' => ['type' => 'string', 'example' => 'PJ-20260701-001'],
                    'tanggal' => ['type' => 'string', 'format' => 'date', 'example' => '2026-07-01'],
                    'kontak_id' => ['type' => 'integer'],
                    'gudang_id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/TransactionItem'],
                    ],
                    'subtotal' => ['type' => 'number', 'format' => 'double'],
                    'diskon' => ['type' => 'number', 'format' => 'double'],
                    'total' => ['type' => 'number', 'format' => 'double'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'approved', 'cancelled'], 'example' => 'draft'],
                    'status_bayar' => ['type' => 'string', 'enum' => ['belum_bayar', 'lunas'], 'example' => 'belum_bayar'],
                    'catatan' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TransactionItem' => [
                'type' => 'object',
                'required' => ['produk_id', 'jumlah', 'harga'],
                'properties' => [
                    'produk_id' => ['type' => 'integer'],
                    'jumlah' => ['type' => 'number', 'format' => 'double', 'example' => 5],
                    'harga' => ['type' => 'number', 'format' => 'double', 'example' => 85000],
                    'subtotal' => ['type' => 'number', 'format' => 'double', 'example' => 425000],
                    'diskon' => ['type' => 'number', 'format' => 'double', 'example' => 0],
                ],
            ],
            'PaginatedResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer', 'example' => 1],
                            'last_page' => ['type' => 'integer', 'example' => 5],
                            'per_page' => ['type' => 'integer', 'example' => 15],
                            'total' => ['type' => 'integer', 'example' => 75],
                        ],
                    ],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Data tidak ditemukan.'],
                    'errors' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'example' => ['email' => ['The email field is required.']],
                    ],
                ],
            ],
            'SuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Berhasil.'],
                    'data' => ['type' => 'object', 'nullable' => true],
                ],
            ],
            'ExportOptionsResponse' => [
                'type' => 'object',
                'properties' => [
                    'role' => [
                        'type' => 'string',
                        'example' => 'super_admin',
                        'description' => 'Current user role',
                    ],
                    'permissions' => [
                        'type' => 'object',
                        'properties' => [
                            'can_export_full_report' => ['type' => 'boolean'],
                            'can_export_pdf' => ['type' => 'boolean'],
                            'can_export_excel' => ['type' => 'boolean'],
                            'can_export_daily_pdf' => ['type' => 'boolean'],
                            'allowed_formats' => [
                                'type' => 'array',
                                'items' => ['type' => 'string', 'enum' => ['pdf', 'excel']],
                            ],
                        ],
                    ],
                    'transaction_types' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string', 'example' => 'all'],
                                'label' => ['type' => 'string', 'example' => 'Semua Transaksi'],
                            ],
                        ],
                        'description' => 'Canonical transaction types for export. Payment types are split: pembayaran_piutang (receivable) and pembayaran_hutang (payable). Legacy pembayaran is deprecated.',
                    ],
                    'status_filters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                            ],
                        ],
                        'description' => 'Status filter options. Use "all" for no filter.',
                    ],
                    'biaya_jenis_filters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'tujuan_kunjungan_filters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'export_formats' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string', 'enum' => ['pdf', 'excel']],
                                'label' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'gudang_options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'nama_gudang' => ['type' => 'string'],
                            ],
                        ],
                        'description' => 'Available gudangs based on user role. Empty for spectator/user roles.',
                    ],
                    'sales_options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                                'gudang_id' => ['type' => 'integer'],
                            ],
                        ],
                        'description' => 'Available sales users based on user role. Empty for spectator/user roles.',
                    ],
                    'defaults' => [
                        'type' => 'object',
                        'properties' => [
                            'transaction_type' => ['type' => 'string', 'example' => 'all'],
                            'status_filter' => ['type' => 'string', 'example' => 'all'],
                            'export_format' => ['type' => 'string', 'example' => 'excel'],
                        ],
                    ],
                ],
            ],
            'ExportRequest' => [
                'type' => 'object',
                'required' => ['transaction_type', 'export_format', 'date_from', 'date_to'],
                'properties' => [
                    'transaction_type' => [
                        'type' => 'string',
                        'enum' => ['all', 'penjualan', 'pembelian', 'biaya', 'kunjungan', 'pembayaran_piutang', 'pembayaran_hutang'],
                        'example' => 'all',
                        'description' => 'Type of transactions to export. Use pembayaran_piutang or pembayaran_hutang for payment reports. Legacy pembayaran is deprecated but still accepted.',
                    ],
                    'export_format' => [
                        'type' => 'string',
                        'enum' => ['pdf', 'excel'],
                        'example' => 'excel',
                    ],
                    'date_from' => [
                        'type' => 'string',
                        'format' => 'date',
                        'example' => '2026-01-01',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'format' => 'date',
                        'example' => '2026-06-30',
                    ],
                    'status_filter' => [
                        'type' => 'string',
                        'nullable' => true,
                        'example' => 'all',
                        'description' => 'Filter by transaction status. Use "all" for no filter.',
                    ],
                    'gudang_id' => [
                        'type' => 'integer',
                        'nullable' => true,
                        'example' => 1,
                    ],
                    'sales_id' => [
                        'type' => 'integer',
                        'nullable' => true,
                        'example' => 5,
                        'description' => 'Filter by sales user (admin/super_admin only)',
                    ],
                    'biaya_jenis' => [
                        'type' => 'string',
                        'nullable' => true,
                        'enum' => ['masuk', 'keluar'],
                        'description' => 'Filter biaya by type',
                    ],
                    'tujuan_filter' => [
                        'type' => 'string',
                        'nullable' => true,
                        'description' => 'Filter kunjungan by purpose',
                    ],
                ],
            ],
        ];
    }

    private function buildPostmanCollection(): array
    {
        return [
            'info' => [
                'name' => 'Hibiscus Efsya POS API v1',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [
                [
                    'name' => 'Auth',
                    'item' => [
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
