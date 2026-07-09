<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Models\Pembelian;
use App\Services\InvoiceEmailService;
use App\Services\PurchaseMoneyCalculator;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreatePembelian extends CreateRecord
{
    use RenamesLampiran, ResolvesApprover;

    protected static string $resource = PembelianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Generate nomor urut harian
        $countToday = Pembelian::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $data['user_id'] = auth()->id();
        $data['status'] = 'Pending';
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = Pembelian::generateNomor(auth()->id(), $noUrut, $now);

        // Set approver_id dan staf_penyetuju
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);
        $data['staf_penyetuju'] = $this->resolveStafPenyetuju($gudangId ?: null);

        // Set tag (nama sales)
        if (empty($data['tag'])) {
            $data['tag'] = $user->name;
        }

        // Set jatuh tempo berdasarkan syarat pembayaran
        $term = $data['syarat_pembayaran'] ?? 'Cash';
        if ($term === 'Cash') {
            $data['tgl_jatuh_tempo'] = null;
        } else {
            $tglJatuhTempo = Carbon::parse($data['tgl_transaksi'] ?? $now);
            $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
            if (isset($days[$term])) {
                $tglJatuhTempo->addDays($days[$term]);
            }
            $data['tgl_jatuh_tempo'] = $tglJatuhTempo;
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

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();

        try {
            InvoiceEmailService::sendCreatedNotification($this->getRecord(), 'pembelian');
        } catch (\Throwable $e) {
            \Log::warning('Email notifikasi pembelian gagal: '.$e->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return PembelianResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getFormAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Permintaan pembelian berhasil diajukan.';
    }
}
