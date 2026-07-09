<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait GeneratesNomorSafely
{
    /**
     * Generate nomor transaksi dengan retry-on-duplicate strategy.
     * Race-safe: menggunakan database lock dan retry jika terjadi duplikasi.
     *
     * @param  string  $prefix  Prefix nomor (INV, PR, PAY, RC, SOP, EXP, VST)
     * @param  int  $userId  User ID
     * @param  Carbon  $createdAt  Tanggal transaksi
     * @param  int  $maxRetries  Maksimal retry attempts
     * @return string Nomor yang unik
     *
     * @throws \RuntimeException Jika gagal generate nomor unik setelah max retries
     */
    protected static function generateNomorWithRetry(
        string $prefix,
        int $userId,
        Carbon $createdAt,
        int $maxRetries = 5
    ): string {
        $dateCode = $createdAt->format('Ymd');
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Gunakan transaction dengan lock untuk atomicity
                return DB::transaction(function () use ($prefix, $userId, $createdAt, $dateCode) {
                    // Hitung nomor urut dengan lock
                    $countToday = static::where('user_id', $userId)
                        ->whereDate('created_at', $createdAt->toDateString())
                        ->lockForUpdate()
                        ->count();

                    $noUrut = $countToday + 1;
                    $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);
                    $nomor = "{$prefix}-{$dateCode}-{$userId}-{$noUrutPadded}";

                    // Cek apakah nomor sudah ada (double-check)
                    $exists = static::where('nomor', $nomor)->exists();
                    if ($exists) {
                        throw new \RuntimeException("Nomor {$nomor} sudah ada");
                    }

                    return $nomor;
                });
            } catch (\RuntimeException $e) {
                // Jika masih ada retry, lanjutkan loop
                if ($attempt >= $maxRetries) {
                    throw new \RuntimeException(
                        "Gagal generate nomor unik setelah {$maxRetries} percobaan untuk {$prefix}"
                    );
                }
                // Tunggu sebentar sebelum retry (exponential backoff)
                usleep($attempt * 50000); // 50ms, 100ms, 150ms, 200ms, 250ms
            }
        }

        throw new \RuntimeException("Gagal generate nomor unik untuk {$prefix}");
    }
}
