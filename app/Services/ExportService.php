<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportService
{
    /**
     * Export semua data transaksi per tahun ke CSV.
     * Mengembalikan filepath ZIP yang berisi CSV + lampiran.
     */
    public function exportYearlyData(int $tahun, ?int $gudangId = null): string
    {
        $tmpDir = storage_path('app/temp/export_'.$tahun.'_'.now()->timestamp);
        $this->ensureDirExists($tmpDir);

        try {
            // 1. Export semua tipe transaksi ke CSV
            $this->exportPenjualanCsv($tahun, $gudangId, $tmpDir);
            $this->exportPembelianCsv($tahun, $gudangId, $tmpDir);
            $this->exportBiayaCsv($tahun, $gudangId, $tmpDir);
            $this->exportKunjunganCsv($tahun, $gudangId, $tmpDir);
            $this->exportPembayaranCsv($tahun, $gudangId, $tmpDir);
            $this->exportPenerimaanBarangCsv($tahun, $gudangId, $tmpDir);
            $this->exportProdukCsv($tmpDir);
            $this->exportKontakCsv($tmpDir);
            $this->exportGudangCsv($tmpDir);

            // 2. Export lampiran (foto) tahun tersebut
            $lampiranDir = $tmpDir.'/lampiran';
            $this->ensureDirExists($lampiranDir);
            $this->collectLampiranFiles($tahun, $lampiranDir);

            // 3. Buat ZIP
            $zipPath = storage_path("app/backup_data_{$tahun}_".now()->format('Y-m-d').'.zip');
            $this->createZip($tmpDir, $zipPath);

            return $zipPath;
        } finally {
            // Cleanup temp directory
            $this->rrmdir($tmpDir);
        }
    }

    // ========================================================================
    // CSV EXPORTERS
    // ========================================================================

    private function exportPenjualanCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('penjualans')
            ->leftJoin('users', 'penjualans.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'penjualans.gudang_id', '=', 'gudangs.id')
            ->whereYear('penjualans.tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereYear('penjualans.created_at', $tahun)
                    ->whereNull('penjualans.tgl_transaksi');
            })
            ->select([
                'penjualans.id', 'penjualans.nomor', 'penjualans.tgl_transaksi',
                'penjualans.pelanggan', 'penjualans.status', 'penjualans.grand_total',
                'penjualans.diskon_akhir', 'penjualans.tax_percentage',
                'users.name as user_name', 'gudangs.nama_gudang',
            ])
            ->orderBy('penjualans.tgl_transaksi');

        if ($gudangId) {
            $query->where('penjualans.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/penjualan.csv', [
            'ID', 'Nomor', 'Tgl Transaksi', 'Pelanggan', 'Status',
            'Grand Total', 'Diskon', 'Tax %', 'Sales', 'Gudang',
        ], $rows);
    }

    private function exportPembelianCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('pembelians')
            ->leftJoin('users', 'pembelians.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'pembelians.gudang_id', '=', 'gudangs.id')
            ->whereYear('pembelians.tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereYear('pembelians.created_at', $tahun)
                    ->whereNull('pembelians.tgl_transaksi');
            })
            ->select([
                'pembelians.id', 'pembelians.nomor', 'pembelians.tgl_transaksi',
                'pembelians.urgensi', 'pembelians.status', 'pembelians.grand_total',
                'users.name as user_name', 'gudangs.nama_gudang',
            ])
            ->orderBy('pembelians.tgl_transaksi');

        if ($gudangId) {
            $query->where('pembelians.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/pembelian.csv', [
            'ID', 'Nomor', 'Tgl Transaksi', 'Urgensi',
            'Status', 'Grand Total', 'Sales', 'Gudang',
        ], $rows);
    }

    private function exportBiayaCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('biayas')
            ->leftJoin('users', 'biayas.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'biayas.gudang_id', '=', 'gudangs.id')
            ->whereYear('biayas.tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereYear('biayas.created_at', $tahun)
                    ->whereNull('biayas.tgl_transaksi');
            })
            ->select([
                'biayas.id', 'biayas.nomor', 'biayas.tgl_transaksi',
                'biayas.jenis_biaya', 'biayas.penerima', 'biayas.status',
                'biayas.grand_total', 'biayas.cara_pembayaran',
                'users.name as user_name', 'gudangs.nama_gudang',
            ])
            ->orderBy('biayas.tgl_transaksi');

        if ($gudangId) {
            $query->where('biayas.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/biaya.csv', [
            'ID', 'Nomor', 'Tgl Transaksi', 'Jenis Biaya', 'Penerima',
            'Status', 'Grand Total', 'Cara Bayar', 'User', 'Gudang',
        ], $rows);
    }

    private function exportKunjunganCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('kunjungans')
            ->leftJoin('users', 'kunjungans.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'kunjungans.gudang_id', '=', 'gudangs.id')
            ->leftJoin('kontaks', 'kunjungans.kontak_id', '=', 'kontaks.id')
            ->whereYear('kunjungans.created_at', $tahun)
            ->select([
                'kunjungans.id', 'kunjungans.nomor', 'kunjungans.tgl_kunjungan',
                'kunjungans.tujuan', 'kunjungans.status',
                'users.name as user_name', 'gudangs.nama_gudang',
                'kontaks.nama as kontak_nama',
            ])
            ->orderBy('kunjungans.tgl_kunjungan');

        if ($gudangId) {
            $query->where('kunjungans.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/kunjungan.csv', [
            'ID', 'Nomor', 'Tgl Kunjungan', 'Tujuan', 'Status',
            'Sales', 'Gudang', 'Kontak',
        ], $rows);
    }

    private function exportPembayaranCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('pembayarans')
            ->leftJoin('users', 'pembayarans.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'pembayarans.gudang_id', '=', 'gudangs.id')
            ->whereYear('pembayarans.tgl_pembayaran', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereYear('pembayarans.created_at', $tahun)
                    ->whereNull('pembayarans.tgl_pembayaran');
            })
            ->select([
                'pembayarans.id', 'pembayarans.nomor', 'pembayarans.tgl_pembayaran',
                'pembayarans.metode_pembayaran', 'pembayarans.jumlah_bayar',
                'pembayarans.status', 'pembayarans.penjualan_id',
                'users.name as user_name', 'gudangs.nama_gudang',
            ])
            ->orderBy('pembayarans.tgl_pembayaran');

        if ($gudangId) {
            $query->where('pembayarans.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/pembayaran.csv', [
            'ID', 'Nomor', 'Tgl Pembayaran', 'Metode', 'Jumlah Bayar',
            'Status', 'Penjualan ID', 'User', 'Gudang',
        ], $rows);
    }

    private function exportPenerimaanBarangCsv(int $tahun, ?int $gudangId, string $dir): void
    {
        $query = DB::table('penerimaan_barangs')
            ->leftJoin('users', 'penerimaan_barangs.user_id', '=', 'users.id')
            ->leftJoin('gudangs', 'penerimaan_barangs.gudang_id', '=', 'gudangs.id')
            ->whereYear('penerimaan_barangs.created_at', $tahun)
            ->select([
                'penerimaan_barangs.id', 'penerimaan_barangs.nomor',
                'penerimaan_barangs.tgl_penerimaan', 'penerimaan_barangs.no_surat_jalan',
                'penerimaan_barangs.status',
                'users.name as user_name', 'gudangs.nama_gudang',
            ])
            ->orderBy('penerimaan_barangs.tgl_penerimaan');

        if ($gudangId) {
            $query->where('penerimaan_barangs.gudang_id', $gudangId);
        }

        $rows = $query->get();
        $this->writeCsv($dir.'/penerimaan_barang.csv', [
            'ID', 'Nomor', 'Tgl Penerimaan', 'No Surat Jalan',
            'Status', 'User', 'Gudang',
        ], $rows);
    }

    private function exportProdukCsv(string $dir): void
    {
        // Produk tidak punya gudang_id langsung - relasi via gudang_produk pivot
        $rows = DB::table('produks')
            ->select([
                'produks.id', 'produks.item_code', 'produks.nama_produk',
                'produks.harga', 'produks.harga_grosir',
                'produks.satuan', 'produks.deskripsi',
            ])
            ->orderBy('produks.nama_produk')
            ->get();

        $this->writeCsv($dir.'/produk.csv', [
            'ID', 'Item Code', 'Nama Produk', 'Harga',
            'Harga Grosir', 'Satuan', 'Deskripsi',
        ], $rows);
    }

    private function exportKontakCsv(string $dir): void
    {
        $rows = DB::table('kontaks')
            ->select(['id', 'kode_kontak', 'nama', 'email', 'no_telp', 'alamat', 'diskon_persen'])
            ->orderBy('nama')
            ->get();

        $this->writeCsv($dir.'/kontak.csv', [
            'ID', 'Kode Kontak', 'Nama', 'Email', 'No Telepon', 'Alamat', 'Diskon %',
        ], $rows);
    }

    private function exportGudangCsv(string $dir): void
    {
        $rows = DB::table('gudangs')
            ->select(['id', 'nama_gudang', 'alamat_gudang'])
            ->orderBy('nama_gudang')
            ->get();

        $this->writeCsv($dir.'/gudang.csv', [
            'ID', 'Nama Gudang', 'Alamat',
        ], $rows);
    }

    // ========================================================================
    // LAMPIRAN COLLECTOR
    // ========================================================================

    private function collectLampiranFiles(int $tahun, string $targetDir): void
    {
        // Cari lampiran dari kolom lampiran_path dan lampiran_paths di semua tipe transaksi
        $lampiranEntries = $this->getAllLampiranPaths($tahun);

        // Filament FileUpload default saves to default disk, which is 'local' (storage/app/private)
        $disk = Storage::disk('local');

        foreach ($lampiranEntries as $entry) {
            $sourcePath = $entry['disk_path']; // ex: lampiran_penjualan/file.jpg
            $type = $entry['type']; // penjualan, pembelian, etc.

            // Handle Filament FileUpload which sometimes stores path as 'lampiran_penjualan/file.jpg'
            // and sometimes just 'file.jpg'. We ensure the prefix is correct.
            $actualSourcePath = $sourcePath;
            if (! $disk->exists($sourcePath)) {
                // Try checking without the directory prefix if it was stored as just filename
                $basename = basename($sourcePath);
                $dirPrefix = explode('/', $sourcePath)[0];
                if ($disk->exists($dirPrefix.'/'.$basename)) {
                    $actualSourcePath = $dirPrefix.'/'.$basename;
                } else {
                    continue; // File not found in storage
                }
            }

            // Buat subfolder berdasarkan tipe
            $typeDir = $targetDir.'/'.str_replace('s', '', $type); // remove trailing 's' for folder name
            $this->ensureDirExists($typeDir);

            // Copy file
            $filename = basename($actualSourcePath);
            $destPath = $typeDir.'/'.$filename;

            // Handle nama file duplikat
            $counter = 1;
            while (file_exists($destPath)) {
                $info = pathinfo($filename);
                $destPath = $typeDir.'/'.$info['filename']."_({$counter}).".($info['extension'] ?? '');
                $counter++;
            }

            copy($disk->path($actualSourcePath), $destPath);
        }
    }

    private function getAllLampiranPaths(int $tahun): array
    {
        $paths = [];

        // Table → [date_column, lampiran_disk_directory]
        // FileUpload stores files at: storage/app/private/{directory}/{filename}
        // DB column lampiran_path/lampiran_paths stores just the filename(s)
        $types = [
            'penjualans' => ['tgl_transaksi', 'lampiran_penjualan'],
            'pembelians' => ['tgl_transaksi', 'lampiran_pembelian'],
            'biayas' => ['tgl_transaksi', 'lampiran_biaya'],
            'kunjungans' => ['tgl_kunjungan', 'lampiran_kunjungan'],
            'pembayarans' => ['tgl_pembayaran', 'lampiran_pembayaran'],
            'penerimaan_barangs' => ['tgl_penerimaan', 'lampiran_penerimaan'],
        ];

        foreach ($types as $table => [$dateField, $diskDir]) {
            try {
                // Use date column if available, fallback to created_at
                $records = DB::table($table)
                    ->where(function ($q) use ($dateField, $tahun) {
                        $q->whereYear($dateField, $tahun)
                            ->orWhere(function ($q2) use ($dateField, $tahun) {
                                $q2->whereNull($dateField)
                                    ->whereYear('created_at', $tahun);
                            });
                    })
                    ->where(function ($q) {
                        $q->whereNotNull('lampiran_path')
                            ->orWhereNotNull('lampiran_paths');
                    })
                    ->select('lampiran_path', 'lampiran_paths')
                    ->get();

                foreach ($records as $record) {
                    if ($record->lampiran_path) {
                        $paths[] = [
                            'disk_path' => $diskDir.'/'.$record->lampiran_path,
                            'type' => $table,
                        ];
                    }

                    if ($record->lampiran_paths) {
                        $files = is_string($record->lampiran_paths)
                            ? json_decode($record->lampiran_paths, true)
                            : $record->lampiran_paths;

                        if (is_array($files)) {
                            foreach ($files as $file) {
                                $paths[] = [
                                    'disk_path' => $diskDir.'/'.$file,
                                    'type' => $table,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Skip tabel yang mungkin belum ada
                continue;
            }
        }

        return $paths;
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function writeCsv(string $filepath, array $headers, iterable $rows): void
    {
        $handle = fopen($filepath, 'w');
        // BOM untuk UTF-8 Excel
        fwrite($handle, "\xEF\xBB\xBF");

        // Header
        fputcsv($handle, $headers, ';');

        // Data
        foreach ($rows as $row) {
            $row = (array) $row;
            $formatted = [];

            foreach ($row as $key => $value) {
                if ($value instanceof Carbon) {
                    $formatted[] = $value->format('Y-m-d');
                } elseif (is_float($value)) {
                    $formatted[] = number_format($value, 2, ',', '.');
                } else {
                    $formatted[] = (string) ($value ?? '');
                }
            }

            fputcsv($handle, $formatted, ';');
        }

        fclose($handle);
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Gagal membuat ZIP: {$zipPath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();
    }

    private function ensureDirExists(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }
}
