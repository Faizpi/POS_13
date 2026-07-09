<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaleCashSettlementService
{
    public const NO_REMAINING_BALANCE_MESSAGE = 'Tidak ada sisa tagihan untuk dilunasi.';

    public function __construct(
        private readonly PaymentSettlementService $paymentSettlementService,
    ) {}

    public function settleRemainingWithCash(Penjualan $penjualan, User $actor): Pembayaran
    {
        return DB::transaction(function () use ($penjualan, $actor): Pembayaran {
            $lockedPenjualan = Penjualan::query()
                ->whereKey($penjualan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingBalance = $this->paymentSettlementService->remainingBalance($lockedPenjualan);
            if ($remainingBalance <= 0) {
                throw new DomainException(self::NO_REMAINING_BALANCE_MESSAGE);
            }

            $this->paymentSettlementService->assertPiutangPaymentCanBeCreated($lockedPenjualan, $remainingBalance);

            $now = Carbon::now();
            $noUrut = Pembayaran::where('user_id', $actor->id)
                ->whereDate('created_at', $now->toDateString())
                ->count() + 1;

            $pembayaran = Pembayaran::create([
                'user_id' => $actor->id,
                'penjualan_id' => $lockedPenjualan->id,
                'gudang_id' => $lockedPenjualan->gudang_id,
                'type' => 'piutang',
                'no_urut_harian' => $noUrut,
                'nomor' => Pembayaran::generateNomor($actor->id, $noUrut, $now),
                'tgl_pembayaran' => $now->toDateString(),
                'metode_pembayaran' => 'Cash',
                'jumlah_bayar' => $remainingBalance,
                'keterangan' => 'Pelunasan cash otomatis dari aksi Tandai Lunas.',
                'status' => 'Pending',
                'lampiran_paths' => [],
            ]);

            return $this->paymentSettlementService->approvePayment($pembayaran, (int) $actor->id);
        });
    }
}
