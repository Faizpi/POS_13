<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\GudangProduk;
use App\Models\Produk;
use App\Models\User;
use App\Services\SalesMoneyCalculator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditPenjualan extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PenjualanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->status, ['Approved', 'Lunas'], true)) {
            if ($this->hasUnsafeApprovedSaleChanges($data)) {
                Notification::make()
                    ->title('Penjualan tidak dapat diedit')
                    ->body('Penjualan Approved/Lunas tidak dapat diedit untuk field stok atau nominal. Batalkan transaksi lalu buat penjualan pengganti.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }

            $data['status'] = $this->record->status;
            $data['approver_id'] = $this->record->approver_id;
            $data['tgl_jatuh_tempo'] = $this->record->tgl_jatuh_tempo;

            return $data;
        }

        $data = $this->recomputeTotals($data);

        // 1. Validasi stok untuk setiap item
        if (! empty($data['items'])) {
            $gudangId = (int) ($data['gudang_id'] ?? $this->record->gudang_id);
            foreach ($data['items'] as $item) {
                $produkId = $item['produk_id'] ?? null;
                $qty = (float) ($item['kuantitas'] ?? 0);
                if (! $produkId) {
                    continue;
                }

                $stokGudang = GudangProduk::where('gudang_id', $gudangId)
                    ->where('produk_id', $produkId)
                    ->first();
                $stokTersedia = $stokGudang?->stok_penjualan ?? 0;

                if ($stokTersedia < $qty) {
                    $namaProduk = $stokGudang?->produk?->nama_produk ?? "ID: {$produkId}";
                    Notification::make()
                        ->title("Stok tidak cukup: {$namaProduk}")
                        ->body("Tersedia: {$stokTersedia}, Diminta: {$qty}")
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        // 2. Tentukan status berdasarkan syarat pembayaran
        $term = $data['syarat_pembayaran'] ?? $this->record->syarat_pembayaran;
        $isCash = ($term == 'Cash');

        if ($isCash) {
            $data['tgl_jatuh_tempo'] = null;
            $data['status'] = 'Pending';
        } else {
            $tglJatuhTempo = Carbon::parse($data['tgl_transaksi'] ?? $this->record->tgl_transaksi);
            $data['status'] = 'Pending';

            if ($term == 'Net 7') {
                $tglJatuhTempo->addDays(7);
            } elseif ($term == 'Net 14') {
                $tglJatuhTempo->addDays(14);
            } elseif ($term == 'Net 30') {
                $tglJatuhTempo->addDays(30);
            } elseif ($term == 'Net 60') {
                $tglJatuhTempo->addDays(60);
            }

            $data['tgl_jatuh_tempo'] = $tglJatuhTempo;
        }

        // 3. Jika status Pending, hitung ulang approver_id (mimik legacy update)
        if ($data['status'] === 'Pending') {
            $user = auth()->user();
            $gudangId = $data['gudang_id'] ?? $this->record->gudang_id;

            if ($user->role === 'user') {
                $adminGudang = User::where('role', 'admin')
                    ->where(function ($q) use ($gudangId) {
                        $q->where('gudang_id', $gudangId)
                            ->orWhereHas('gudangs', function ($sub) use ($gudangId) {
                                $sub->where('gudangs.id', $gudangId);
                            });
                    })
                    ->first();

                $data['approver_id'] = $adminGudang ? $adminGudang->id :
                    (User::where('role', 'super_admin')->first()->id ?? null);
            } elseif ($user->role === 'admin') {
                $data['approver_id'] = User::where('role', 'super_admin')->first()->id ?? null;
            }
            // For super_admin, approver_id is handled by the form (disabled field keeps existing value)
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recomputeTotals(array $data): array
    {
        $rawItems = $this->form->getRawState()['items'] ?? [];
        $items = array_values($data['items'] ?? $rawItems);
        $produkIds = collect($items)->pluck('produk_id')->filter()->values()->all();
        $produks = Produk::whereIn('id', $produkIds)->get()->keyBy('id');

        $calculatorItems = collect($items)
            ->map(function (array $item) use ($produks): array {
                $produk = $produks->get($item['produk_id'] ?? null);

                if (! $produk) {
                    throw ValidationException::withMessages([
                        'items' => ['Produk tidak ditemukan.'],
                    ]);
                }

                unset($item['harga_satuan'], $item['unit_price'], $item['price']);

                $item['kuantitas'] = (string) ($item['kuantitas'] ?? 0);
                $item['diskon'] = (string) ($item['diskon'] ?? 0);
                $item['diskon_nominal'] = (string) ($item['diskon_nominal'] ?? 0);

                return $item + [
                    'harga_retail' => $produk->harga,
                    'harga_grosir' => ((float) ($produk->harga_grosir ?? 0) > 0) ? $produk->harga_grosir : null,
                    'harga' => $produk->harga,
                    'unit' => $item['unit'] ?? $produk->satuan,
                    'deskripsi' => $item['deskripsi'] ?? null,
                ];
            })
            ->all();

        try {
            $totals = app(SalesMoneyCalculator::class)->calculateTotals(
                $calculatorItems,
                (string) ($data['diskon_akhir'] ?? 0),
                (string) ($data['tax_percentage'] ?? 0),
                (string) ($data['biaya_pengiriman'] ?? 0),
                $data['tipe_harga'] ?? $this->record->tipe_harga ?? 'retail',
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'items' => [$e->getMessage()],
            ]);
        }

        $data['items'] = collect($totals['items'])
            ->map(fn (array $item, int $index): array => array_merge($items[$index], [
                'produk_id' => $item['produk_id'],
                'deskripsi' => $item['deskripsi'],
                'kuantitas' => $item['kuantitas'],
                'unit' => $item['unit'],
                'harga_satuan' => $item['harga_satuan'],
                'diskon' => $item['diskon'],
                'diskon_nominal' => $item['diskon_nominal'],
                'jumlah_baris' => $item['jumlah_baris'],
            ]))
            ->all();
        $data['diskon_akhir'] = $totals['diskon_akhir'];
        $data['tax_percentage'] = $totals['tax_percentage'];
        $data['biaya_pengiriman'] = $totals['biaya_pengiriman'];
        $data['grand_total'] = $totals['grand_total'];

        $this->form->partialRawState([
            'items' => $data['items'],
            'diskon_akhir' => $data['diskon_akhir'],
            'tax_percentage' => $data['tax_percentage'],
            'biaya_pengiriman' => $data['biaya_pengiriman'],
            'grand_total' => $data['grand_total'],
        ]);

        return $data;
    }

    private function hasUnsafeApprovedSaleChanges(array $data): bool
    {
        foreach (['gudang_id', 'tipe_harga', 'syarat_pembayaran'] as $field) {
            if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $this->record->{$field}) {
                return true;
            }
        }

        foreach (['tax_percentage', 'diskon_akhir', 'grand_total'] as $field) {
            if (array_key_exists($field, $data) && round((float) $data[$field], 2) !== round((float) $this->record->{$field}, 2)) {
                return true;
            }
        }

        if (array_key_exists('items', $data)) {
            return $this->normalizeSubmittedItems($data['items']) !== $this->normalizePersistedItems();
        }

        return false;
    }

    private function normalizeSubmittedItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'produk_id' => (int) ($item['produk_id'] ?? 0),
                'kuantitas' => round((float) ($item['kuantitas'] ?? 0), 2),
                'harga_satuan' => round((float) ($item['harga_satuan'] ?? 0), 2),
                'diskon' => round((float) ($item['diskon'] ?? 0), 2),
                'diskon_nominal' => round((float) ($item['diskon_nominal'] ?? 0), 2),
                'jumlah_baris' => round((float) ($item['jumlah_baris'] ?? 0), 2),
            ])
            ->sortBy('produk_id')
            ->values()
            ->all();
    }

    private function normalizePersistedItems(): array
    {
        return $this->record->items()
            ->get()
            ->map(fn ($item): array => [
                'produk_id' => (int) $item->produk_id,
                'kuantitas' => round((float) $item->kuantitas, 2),
                'harga_satuan' => round((float) $item->harga_satuan, 2),
                'diskon' => round((float) ($item->diskon ?? 0), 2),
                'diskon_nominal' => round((float) ($item->diskon_nominal ?? 0), 2),
                'jumlah_baris' => round((float) $item->jumlah_baris, 2),
            ])
            ->sortBy('produk_id')
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali_detail')
                ->label('Lihat Detail')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => PenjualanResource::getUrl('view', ['record' => $this->getRecord()])),

            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePenjualan($this->getRecord())),
        ];
    }

    /**
     * Setelah simpan: redirect kembali ke halaman view (detail).
     */
    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PenjualanResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data penjualan berhasil diperbarui.';
    }
}
