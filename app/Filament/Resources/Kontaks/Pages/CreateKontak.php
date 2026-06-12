<?php

namespace App\Filament\Resources\Kontaks\Pages;

use App\Filament\Resources\Kontaks\KontakResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKontak extends CreateRecord
{
    protected static string $resource = KontakResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Gap A3: Set created_by (legacy always sets this)
        $data['created_by'] = $user->id;

        // Auto-assign gudang_id jika tidak diisi (non-super_admin)
        if (empty($data['gudang_id'])) {
            $data['gudang_id'] = $user?->getCurrentGudang()?->id;
        }

        // Gap A5: Normalize phone number 08xxx → 628xxx (legacy normalizePhone)
        if (!empty($data['no_telp'])) {
            $data['no_telp'] = self::normalizePhone($data['no_telp']);
        }

        return $data;
    }

    /**
     * Normalize phone format: 08xxx → 628xxx, +628xxx → 628xxx
     * Sesuai legacy normalizePhone() di KontakController.
     */
    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\.\(\)]+/', '', $phone);
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }
        if (str_starts_with($phone, '8') && strlen($phone) >= 9 && strlen($phone) <= 13) {
            $phone = '62' . $phone;
        }
        return $phone;
    }
}
