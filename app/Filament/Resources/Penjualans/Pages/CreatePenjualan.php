<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\GudangProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Services\InvoiceEmailService;
use App\Services\SalesMoneyCalculator;
use App\Services\WhatsappNotificationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreatePenjualan extends CreateRecord
{
    use RenamesLampiran, ResolvesApprover;

    protected static string $resource = PenjualanResource::class;

    /**
     * Validasi stok sebelum create — dijalankan sebelum record disimpan.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // 1. Generate nomor urut harian
        $countToday = Penjualan::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $data['user_id'] = auth()->id();
        $data['status'] = 'Pending'; // Cash settlement requires an explicit approved Pembayaran record before Lunas.
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = Penjualan::generateNomor(auth()->id(), $noUrut, $now);

        // 2. Set approver_id
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);

        // 3. Set tag (nama sales)
        if (empty($data['tag'])) {
            $data['tag'] = $user->name;
        }

        // 4. Set tanggal jatuh tempo berdasarkan syarat pembayaran (Cash = null, Net X = hitung tanggal)
        $term = $data['syarat_pembayaran'] ?? 'Cash'; // Default ke Cash jika tidak diisi
        $isCash = ($term == 'Cash');

        if ($isCash) {
            $data['tgl_jatuh_tempo'] = null;
        } else {
            $tglJatuhTempo = Carbon::parse($data['tgl_transaksi'] ?? $now);
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

        $data = $this->recomputeTotals($data);

        // 5. Validasi stok untuk setiap item
        if (! empty($data['items'])) {
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
                $data['tipe_harga'] ?? 'retail',
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

    /**
     * Setelah record tersimpan: kirim email notifikasi.
     */
    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();

        try {
            InvoiceEmailService::sendCreatedNotification($this->getRecord(), 'penjualan');
        } catch (\Throwable $e) {
            \Log::warning('Email notifikasi penjualan gagal: '.$e->getMessage());
        }

        try {
            WhatsappNotificationService::sendPenjualanCreated($this->getRecord());
        } catch (\Throwable $e) {
            \Log::warning('WA notifikasi penjualan gagal: '.$e->getMessage());
        }
    }

    /**
     * Redirect ke halaman view (detail) setelah create — bukan ke index.
     */
    protected function getRedirectUrl(): string
    {
        return PenjualanResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getFormAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Penjualan berhasil diajukan dan menunggu approval.';
    }
}
