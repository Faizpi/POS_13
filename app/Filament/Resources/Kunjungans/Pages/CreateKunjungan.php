<?php

namespace App\Filament\Resources\Kunjungans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\Kunjungans\KunjunganResource;
use App\Models\GudangProduk;
use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\Produk;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateKunjungan extends CreateRecord
{
    use RenamesLampiran, ResolvesApprover;

    protected static string $resource = KunjunganResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $tujuan = $data['tujuan'] ?? '';

        if (in_array($tujuan, ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample'])) {
            if (empty($data['items']) || count($data['items']) === 0) {
                Notification::make()
                    ->danger()
                    ->title('Produk wajib diisi untuk kunjungan tipe ini.')
                    ->send();
                $this->halt();
            }
        }

        if (in_array($tujuan, ['Promo Gratis', 'Promo Sample']) && ! empty($data['items'])) {
            $gudangId = $data['gudang_id'] ?? auth()->user()?->getCurrentGudang()?->id;

            if ($gudangId) {
                $stokField = $tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
                $stokLabel = $tujuan === 'Promo Gratis' ? 'stok gratis' : 'stok sample';

                foreach ($data['items'] as $item) {
                    $produkId = $item['produk_id'] ?? null;
                    if ($produkId) {
                        $qty = $item['jumlah'] ?? 1;
                        $stokAvailable = GudangProduk::where('gudang_id', $gudangId)
                            ->where('produk_id', $produkId)
                            ->value($stokField) ?? 0;

                        if ($qty > $stokAvailable) {
                            $namaProduk = Produk::find($produkId)->nama_produk ?? 'Produk';
                            Notification::make()
                                ->danger()
                                ->title("Qty {$namaProduk} ({$qty}) melebihi {$stokLabel} yang tersedia ({$stokAvailable}).")
                                ->send();
                            $this->halt();
                        }
                    }
                }
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Generate nomor urut harian
        $countToday = Kunjungan::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $data['user_id'] = auth()->id();
        $data['status'] = $user->isSuperAdmin() ? 'Approved' : 'Pending';
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = Kunjungan::generateNomor(auth()->id(), $noUrut, $now);

        // Autofill data sales dari user login
        if (empty($data['sales_nama'])) {
            $data['sales_nama'] = $user->name;
        }
        if (empty($data['sales_no_telepon'])) {
            $data['sales_no_telepon'] = $user->no_telp;
        }
        if (empty($data['sales_alamat'])) {
            $data['sales_alamat'] = $user->alamat;
        }

        // Set gudang dari user jika tidak diisi
        if (empty($data['gudang_id'])) {
            $data['gudang_id'] = $user?->getCurrentGudang()?->id;
        }

        // Set approver_id
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return KunjunganResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Kunjungan berhasil diajukan dan menunggu approval.';
    }

    /**
     * Inject barcode scanner modal + geolocation script + scanner data into page footer
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    public function getExtraAttributes(): array
    {
        return [];
    }

    /**
     * Pass kontak and produk data for scanner lookup into the view.
     * Injected as JSON in the rendered HTML via a blade include.
     */
    protected function getViewData(): array
    {
        $kontaks = Kontak::select('id', 'nama', 'kode_kontak')->get()
            ->map(fn ($k) => ['id' => $k->id, 'kode' => $k->kode_kontak ?? '', 'nama' => $k->nama]);

        $produks = Produk::select('id', 'nama_produk', 'item_code')->get()
            ->map(fn ($p) => ['id' => $p->id, 'kode' => $p->item_code ?? '', 'nama' => $p->nama_produk ?? '']);

        return [
            'scannerKontaks' => $kontaks,
            'scannerProduks' => $produks,
        ];
    }
}
