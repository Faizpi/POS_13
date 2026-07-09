<?php

namespace App\Services;

use App\Models\TutupBuku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TutupBukuService
{
    /**
     * Jalankan proses Tutup Buku Tahunan.
     */
    public function execute(int $tahun, int $userId, ?string $notes = null): TutupBuku
    {
        // 1. Validasi awal
        $this->validateClosing($tahun);

        // 2. Buat record tutup buku dengan status processing
        $tutupBuku = TutupBuku::create([
            'tahun' => $tahun,
            'status' => 'processing',
            'closed_by' => $userId,
            'notes' => $notes,
        ]);

        try {
            DB::beginTransaction();

            $metadata = [
                'started_at' => now()->toDateTimeString(),
                'summary' => [],
            ];

            // 3. Ambil Snapshot Stok Akhir Tahun sebagai metadata awal
            $metadata['stok_snapshot'] = $this->takeStockSnapshot();

            // 4. Pindahkan data ke Archive & hapus dari Main Table
            $metadata['summary']['penjualan'] = $this->archivePenjualan($tahun);
            $metadata['summary']['pembelian'] = $this->archivePembelian($tahun);
            $metadata['summary']['biaya'] = $this->archiveBiaya($tahun);
            $metadata['summary']['kunjungan'] = $this->archiveKunjungan($tahun);
            $metadata['summary']['pembayaran'] = $this->archivePembayaran($tahun);
            $metadata['summary']['penerimaan_barang'] = $this->archivePenerimaanBarang($tahun);

            // 5. Update status menjadi completed
            $metadata['completed_at'] = now()->toDateTimeString();

            $tutupBuku->update([
                'status' => 'completed',
                'closed_at' => now(),
                'metadata' => $metadata,
            ]);

            DB::commit();

            return $tutupBuku;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Tutup Buku Tahunan {$tahun} Gagal: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $tutupBuku->update([
                'status' => 'failed',
                'notes' => ($tutupBuku->notes ? $tutupBuku->notes."\n" : '').'Proses gagal: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validasi apakah tahun ini bisa ditutup.
     */
    public function validateClosing(int $tahun): void
    {
        // Cek apakah tahun ini sudah ditutup
        if (TutupBuku::isYearClosed($tahun)) {
            throw new \InvalidArgumentException("Tahun {$tahun} sudah ditutup sebelumnya.");
        }

        // Cek tahun-tahun sebelumnya apakah sudah ditutup (tutup buku harus berurutan)
        $lastClosedYear = TutupBuku::getLastClosedYear();
        if ($lastClosedYear && $tahun < $lastClosedYear) {
            throw new \InvalidArgumentException("Tidak bisa menutup tahun {$tahun} karena tahun {$lastClosedYear} sudah ditutup.");
        }

        // Cek transaksi pending di tahun yang akan ditutup
        $tables = [
            'penjualans' => 'tgl_transaksi',
            'pembelians' => 'tgl_transaksi',
            'biayas' => 'tgl_transaksi',
            'kunjungans' => 'created_at',
            'pembayarans' => 'tgl_pembayaran',
            'penerimaan_barangs' => 'created_at',
        ];

        foreach ($tables as $table => $dateCol) {
            $pendingCount = DB::table($table)
                ->where(function ($q) use ($dateCol, $tahun) {
                    $q->whereYear($dateCol, $tahun)
                        ->orWhere(function ($q2) use ($dateCol, $tahun) {
                            $q2->whereNull($dateCol)
                                ->whereYear('created_at', $tahun);
                        });
                })
                ->where('status', 'Pending')
                ->count();

            if ($pendingCount > 0) {
                throw new \InvalidArgumentException(
                    "Terdapat {$pendingCount} transaksi berstatus 'Pending' pada tabel ".ucfirst($table).'. Semua transaksi harus diproses (Lunas/Approved/Rejected/Canceled) sebelum tutup buku.'
                );
            }
        }
    }

    private function takeStockSnapshot(): array
    {
        return DB::table('gudang_produk')
            ->leftJoin('gudangs', 'gudang_produk.gudang_id', '=', 'gudangs.id')
            ->leftJoin('produks', 'gudang_produk.produk_id', '=', 'produks.id')
            ->select([
                'gudang_produk.gudang_id',
                'gudangs.nama_gudang',
                'gudang_produk.produk_id',
                'produks.nama as nama_produk',
                'produks.kode as kode_produk',
                'gudang_produk.stok',
            ])
            ->get()
            ->toArray();
    }

    // ========================================================================
    // ARCHIVE IMPLEMENTATIONS
    // ========================================================================

    private function archivePenjualan(int $tahun): array
    {
        $records = DB::table('penjualans')
            ->whereYear('tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_transaksi')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            // Pindahkan parent
            $archiveParentId = DB::table('archive_penjualans')->insertGetId([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'tipe_harga' => $record->tipe_harga,
                'pelanggan' => $record->pelanggan,
                'no_telepon' => $record->no_telepon,
                'alamat_penagihan' => $record->alamat_penagihan,
                'tgl_transaksi' => $record->tgl_transaksi,
                'tgl_jatuh_tempo' => $record->tgl_jatuh_tempo,
                'syarat_pembayaran' => $record->syarat_pembayaran,
                'no_referensi' => $record->no_referensi,
                'tag' => $record->tag,
                'koordinat' => $record->koordinat,
                'memo' => $record->memo,
                'lampiran_path' => $record->lampiran_path,
                'lampiran_paths' => $record->lampiran_paths,
                'status' => $record->status,
                'diskon_akhir' => $record->diskon_akhir,
                'tax_percentage' => $record->tax_percentage,
                'grand_total' => $record->grand_total,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            // Pindahkan items
            $items = DB::table('penjualan_items')->where('penjualan_id', $record->id)->get();
            foreach ($items as $item) {
                DB::table('archive_penjualan_items')->insert([
                    'original_id' => $item->id,
                    'archive_tahun' => $tahun,
                    'archive_penjualan_id' => $archiveParentId,
                    'produk_id' => $item->produk_id,
                    'deskripsi' => $item->deskripsi,
                    'kuantitas' => $item->kuantitas,
                    'unit' => $item->unit,
                    'harga_satuan' => $item->harga_satuan,
                    'diskon' => $item->diskon,
                    'diskon_nominal' => $item->diskon_nominal,
                    'batch_number' => $item->batch_number,
                    'expired_date' => $item->expired_date,
                    'jumlah_baris' => $item->jumlah_baris,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            // Hapus original
            DB::table('penjualans')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }

    private function archivePembelian(int $tahun): array
    {
        $records = DB::table('pembelians')
            ->whereYear('tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_transaksi')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            $archiveParentId = DB::table('archive_pembelians')->insertGetId([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'staf_penyetuju' => $record->staf_penyetuju,
                'email_penyetuju' => $record->email_penyetuju,
                'tgl_transaksi' => $record->tgl_transaksi,
                'tgl_jatuh_tempo' => $record->tgl_jatuh_tempo,
                'syarat_pembayaran' => $record->syarat_pembayaran,
                'urgensi' => $record->urgensi,
                'tahun_anggaran' => $record->tahun_anggaran,
                'tag' => $record->tag,
                'koordinat' => $record->koordinat,
                'memo' => $record->memo,
                'lampiran_path' => $record->lampiran_path,
                'lampiran_paths' => $record->lampiran_paths,
                'status' => $record->status,
                'diskon_akhir' => $record->diskon_akhir,
                'tax_percentage' => $record->tax_percentage,
                'grand_total' => $record->grand_total,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            $items = DB::table('pembelian_items')->where('pembelian_id', $record->id)->get();
            foreach ($items as $item) {
                DB::table('archive_pembelian_items')->insert([
                    'original_id' => $item->id,
                    'archive_tahun' => $tahun,
                    'archive_pembelian_id' => $archiveParentId,
                    'produk_id' => $item->produk_id,
                    'deskripsi' => $item->deskripsi,
                    'kuantitas' => $item->kuantitas,
                    'unit' => $item->unit,
                    'harga_satuan' => $item->harga_satuan,
                    'diskon' => $item->diskon,
                    'jumlah_baris' => $item->jumlah_baris,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            DB::table('pembelians')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }

    private function archiveBiaya(int $tahun): array
    {
        $records = DB::table('biayas')
            ->whereYear('tgl_transaksi', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_transaksi')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            $archiveParentId = DB::table('archive_biayas')->insertGetId([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'jenis_biaya' => $record->jenis_biaya,
                'bayar_dari' => $record->bayar_dari,
                'penerima' => $record->penerima,
                'alamat_penagihan' => $record->alamat_penagihan,
                'tgl_transaksi' => $record->tgl_transaksi,
                'cara_pembayaran' => $record->cara_pembayaran,
                'tag' => $record->tag,
                'koordinat' => $record->koordinat,
                'memo' => $record->memo,
                'lampiran_path' => $record->lampiran_path,
                'lampiran_paths' => $record->lampiran_paths,
                'status' => $record->status,
                'tax_percentage' => $record->tax_percentage,
                'grand_total' => $record->grand_total,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            $items = DB::table('biaya_items')->where('biaya_id', $record->id)->get();
            foreach ($items as $item) {
                DB::table('archive_biaya_items')->insert([
                    'original_id' => $item->id,
                    'archive_tahun' => $tahun,
                    'archive_biaya_id' => $archiveParentId,
                    'kategori' => $item->kategori,
                    'deskripsi' => $item->deskripsi,
                    'jumlah' => $item->jumlah,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            DB::table('biayas')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }

    private function archiveKunjungan(int $tahun): array
    {
        $records = DB::table('kunjungans')
            ->whereYear('tgl_kunjungan', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_kunjungan')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            $archiveParentId = DB::table('archive_kunjungans')->insertGetId([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'kontak_id' => $record->kontak_id,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'sales_nama' => $record->sales_nama,
                'sales_no_telepon' => $record->sales_no_telepon,
                'sales_alamat' => $record->sales_alamat,
                'tgl_kunjungan' => $record->tgl_kunjungan,
                'tujuan' => $record->tujuan,
                'koordinat' => $record->koordinat,
                'memo' => $record->memo,
                'lampiran_path' => $record->lampiran_path,
                'lampiran_paths' => $record->lampiran_paths,
                'status' => $record->status,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            $items = DB::table('kunjungan_items')->where('kunjungan_id', $record->id)->get();
            foreach ($items as $item) {
                DB::table('archive_kunjungan_items')->insert([
                    'original_id' => $item->id,
                    'archive_tahun' => $tahun,
                    'archive_kunjungan_id' => $archiveParentId,
                    'produk_id' => $item->produk_id,
                    'jumlah' => $item->jumlah,
                    'batch_number' => $item->batch_number,
                    'expired_date' => $item->expired_date,
                    'keterangan' => $item->keterangan,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            DB::table('kunjungans')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }

    private function archivePembayaran(int $tahun): array
    {
        $records = DB::table('pembayarans')
            ->whereYear('tgl_pembayaran', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_pembayaran')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            // Cari original_id dari archive parent penjualan karena relationnya foreign key
            // Jika penjualan asal juga di-archive, kita cari record barunya
            $archivePenjualan = DB::table('archive_penjualans')
                ->where('original_id', $record->penjualan_id)
                ->first();

            $penjualanId = $archivePenjualan ? $archivePenjualan->id : $record->penjualan_id;

            DB::table('archive_pembayarans')->insert([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'penjualan_id' => $penjualanId,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'tgl_pembayaran' => $record->tgl_pembayaran,
                'metode_pembayaran' => $record->metode_pembayaran,
                'jumlah_bayar' => $record->jumlah_bayar,
                'bukti_bayar' => $record->bukti_bayar,
                'lampiran_paths' => $record->lampiran_paths,
                'keterangan' => $record->keterangan,
                'status' => $record->status,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            DB::table('pembayarans')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }

    private function archivePenerimaanBarang(int $tahun): array
    {
        $records = DB::table('penerimaan_barangs')
            ->whereYear('tgl_penerimaan', $tahun)
            ->orWhere(function ($q) use ($tahun) {
                $q->whereNull('tgl_penerimaan')
                    ->whereYear('created_at', $tahun);
            })
            ->get();

        $count = 0;
        foreach ($records as $record) {
            $archivePembelian = DB::table('archive_pembelians')
                ->where('original_id', $record->pembelian_id)
                ->first();

            $pembelianId = $archivePembelian ? $archivePembelian->id : $record->pembelian_id;

            $archiveParentId = DB::table('archive_penerimaan_barangs')->insertGetId([
                'original_id' => $record->id,
                'archive_tahun' => $tahun,
                'uuid' => $record->uuid,
                'user_id' => $record->user_id,
                'approver_id' => $record->approver_id,
                'gudang_id' => $record->gudang_id,
                'pembelian_id' => $pembelianId,
                'no_urut_harian' => $record->no_urut_harian,
                'nomor' => $record->nomor,
                'tgl_penerimaan' => $record->tgl_penerimaan,
                'no_surat_jalan' => $record->no_surat_jalan,
                'lampiran_paths' => $record->lampiran_paths,
                'keterangan' => $record->keterangan,
                'status' => $record->status,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            $items = DB::table('penerimaan_barang_items')->where('penerimaan_barang_id', $record->id)->get();
            foreach ($items as $item) {
                DB::table('archive_penerimaan_barang_items')->insert([
                    'original_id' => $item->id,
                    'archive_tahun' => $tahun,
                    'archive_penerimaan_barang_id' => $archiveParentId,
                    'produk_id' => $item->produk_id,
                    'qty_diterima' => $item->qty_diterima,
                    'qty_reject' => $item->qty_reject,
                    'tipe_stok' => $item->tipe_stok,
                    'batch_number' => $item->batch_number,
                    'expired_date' => $item->expired_date,
                    'keterangan' => $item->keterangan,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            DB::table('penerimaan_barangs')->where('id', $record->id)->delete();
            $count++;
        }

        return ['archived' => $count];
    }
}
