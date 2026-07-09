<?php

namespace App\Filament\Resources\Pembayarans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePembayaran extends CreateRecord
{
    use RenamesLampiran, ResolvesApprover;

    protected static string $resource = PembayaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Generate nomor urut harian
        $countToday = Pembayaran::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $data['user_id'] = auth()->id();
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = Pembayaran::generateNomor(auth()->id(), $noUrut, $now);

        // Tentukan gudang (fallback dari penjualan pertama jika tidak ada di form)
        if (empty($data['gudang_id']) && ! empty($data['penjualan_ids'])) {
            $firstId = (array) $data['penjualan_ids'];
            $penjualan = Penjualan::find($firstId[0]);
            $data['gudang_id'] = $penjualan?->gudang_id;
        }

        $gudangId = (int) ($data['gudang_id'] ?? 0);

        // Super admin: langsung Approved, lainnya: Pending
        if ($user->isSuperAdmin()) {
            $data['status'] = 'Approved';
            $data['approver_id'] = $user->id;
        } else {
            $data['status'] = 'Pending';
            $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);
        }

        return $data;
    }

    /**
     * Gap 3 fix: Override handleRecordCreation untuk distribusi ke MULTIPLE invoice.
     * Setiap invoice dipilih akan mendapat record Pembayaran terpisah (suffix -A, -B, dst).
     * Sisa bayar didistribusikan proporsional sesuai sisa hutang tiap invoice.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $penjualanIds = (array) ($data['penjualan_ids'] ?? []);
        unset($data['penjualan_ids']);

        // Jika tidak ada invoice yang dipilih, buat record kosong untuk menghindari fatal error
        if (empty($penjualanIds)) {
            return Pembayaran::create($data);
        }

        $jumlahBayar = (float) ($data['jumlah_bayar'] ?? 0);
        $lampiranPaths = $data['lampiran_paths'] ?? [];

        // Hitung sisa hutang per invoice
        $penjualanDetails = [];
        foreach ($penjualanIds as $penjualanId) {
            $penjualan = Penjualan::find($penjualanId);
            if (! $penjualan) {
                continue;
            }

            $sudahBayar = (float) Pembayaran::where('penjualan_id', $penjualanId)
                ->where('status', 'Approved')
                ->sum('jumlah_bayar');
            $sisa = max(0, (float) $penjualan->grand_total - $sudahBayar);

            if ($sisa > 0) {
                $penjualanDetails[] = ['penjualan' => $penjualan, 'sisa' => $sisa];
            }
        }

        $firstRecord = null;
        $sisaBayar = $jumlahBayar;

        DB::beginTransaction();
        try {
            foreach ($penjualanDetails as $index => $detail) {
                if ($sisaBayar <= 0) {
                    break;
                }

                $bayarUntukIni = min($sisaBayar, $detail['sisa']);
                $sisaBayar -= $bayarUntukIni;

                // Suffix -A, -B, dst jika multi-invoice; tanpa suffix jika hanya 1
                $nomorPembayaran = count($penjualanDetails) > 1
                    ? $data['nomor'].'-'.chr(65 + $index)
                    : $data['nomor'];

                $pembayaran = Pembayaran::create(array_merge($data, [
                    'penjualan_id' => $detail['penjualan']->id,
                    'nomor' => $nomorPembayaran,
                    'no_urut_harian' => ($data['no_urut_harian'] ?? 1) + $index,
                    'jumlah_bayar' => $bayarUntukIni,
                    // Lampiran hanya di record pertama
                    'lampiran_paths' => $index === 0 ? $lampiranPaths : [],
                ]));

                // Jika langsung Approved (super_admin), cek apakah invoice sudah lunas
                if ($data['status'] === 'Approved') {
                    $totalBayarSetelah = (float) Pembayaran::where('penjualan_id', $detail['penjualan']->id)
                        ->where('status', 'Approved')
                        ->sum('jumlah_bayar');

                    if ($totalBayarSetelah >= (float) $detail['penjualan']->grand_total) {
                        $detail['penjualan']->update(['status' => 'Lunas']);
                    }
                }

                if ($firstRecord === null) {
                    $firstRecord = $pembayaran;
                }
            }

            // Jika ada kelebihan bayar, catat di keterangan record pertama
            if ($sisaBayar > 0 && $firstRecord) {
                $ket = $firstRecord->keterangan;
                $firstRecord->update([
                    'keterangan' => ($ket ? $ket.'. ' : '').'Kelebihan bayar: '.format_rupiah($sisaBayar),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Kembalikan record pertama agar Filament bisa redirect ke view
        return $firstRecord ?? Pembayaran::create(array_merge($data, [
            'penjualan_id' => $penjualanIds[0] ?? null,
        ]));
    }

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PembayaranResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $record = $this->getRecord();
        $status = $record->status === 'Approved'
            ? 'Pembayaran langsung disetujui.'
            : 'Pembayaran berhasil diajukan untuk approval.';

        return $status;
    }
}
