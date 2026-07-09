<?php

namespace App\Filament\Resources\PembayaranHutangs\Pages;

use App\Filament\Resources\PembayaranHutangs\PembayaranHutangResource;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Services\PaymentSettlementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePembayaranHutang extends CreateRecord
{
    protected static string $resource = PembayaranHutangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Set type & nomor
        $data['type'] = 'hutang';
        $data['user_id'] = $user->id;
        $data['uuid'] = (string) Str::uuid();
        $data['status'] = $data['status'] ?? 'Pending';

        // Generate nomor
        $countToday = Pembayaran::where('type', 'hutang')
            ->whereDate('created_at', now())
            ->count();
        $data['nomor'] = 'PAYH-'.now()->format('Ymd').'-'.$user->id.'-'.str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);

        // Set pembelian_id dari pembelian_ids[0] untuk memenuhi kolom NOT NULL.
        // Pembayaran didistribusikan penuh ke 1 invoice; untuk distribusi multi-invoice,
        // gunakan RelationManager di ViewPembelian.
        if (! empty($data['pembelian_ids'])) {
            $first = (array) $data['pembelian_ids'];
            $data['pembelian_id'] = reset($first);
        }

        if (! empty($data['pembelian_id'])) {
            $pembelian = Pembelian::findOrFail($data['pembelian_id']);
            app(PaymentSettlementService::class)->assertHutangPaymentCanBeCreated($pembelian, $data['jumlah_bayar']);
        }

        // Hapus field sementara
        unset($data['sisa_hutang_preview'], $data['pembelian_ids']);

        return $data;
    }
}
