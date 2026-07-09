<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Services\PurchaseMoneyCalculator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditPembelian extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PembelianResource::class;

    use ResolvesApprover;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $term = $data['syarat_pembayaran'] ?? $this->record->syarat_pembayaran;
        $isCash = ($term == 'Cash');

        if ($isCash) {
            $data['tgl_jatuh_tempo'] = null;
            $data['status'] = 'Lunas';
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

        // Jika status Pending, hitung ulang approver_id dan staf_penyetuju
        if ($data['status'] === 'Pending') {
            $gudangId = $data['gudang_id'] ?? $this->record->gudang_id;
            $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);
            $data['staf_penyetuju'] = $this->resolveStafPenyetuju($gudangId ?: null);
        }

        return $this->recomputeTotals($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recomputeTotals(array $data): array
    {
        $rawItems = $this->form->getRawState()['items'] ?? [];
        $items = array_values($data['items'] ?? $rawItems);
        $calculatorItems = collect($items)
            ->map(function (array $item): array {
                $item['kuantitas'] = (string) ($item['kuantitas'] ?? 0);
                $item['harga_satuan'] = (string) ($item['harga_satuan'] ?? 0);
                $item['diskon'] = (string) ($item['diskon'] ?? 0);
                $item['diskon_nominal'] = '0';

                return $item;
            })
            ->all();

        try {
            $totals = app(PurchaseMoneyCalculator::class)->calculateTotals(
                $calculatorItems,
                (string) ($data['diskon_akhir'] ?? 0),
                (string) ($data['tax_percentage'] ?? 0),
                (string) ($data['biaya_pengiriman'] ?? 0),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                str_contains($e->getMessage(), 'Diskon akhir') ? 'diskon_akhir' : 'items' => [$e->getMessage()],
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali_detail')
                ->label('Lihat Detail')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => PembelianResource::getUrl('view', ['record' => $this->getRecord()])),

            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePembelian($this->getRecord())),
        ];
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PembelianResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data pembelian berhasil diperbarui.';
    }
}
