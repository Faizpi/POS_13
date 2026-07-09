<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Biayas\BiayaResource;
use App\Filament\Resources\Kunjungans\KunjunganResource;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\Biaya;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AktivitasTerbaru extends BaseTableWidget
{
    protected static ?string $heading = 'Aktivitas Terbaru';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Penjualan::query()->whereRaw('1=0');
    }

    public function getTableRecords(): Collection
    {
        $limit = 10;
        $user = auth()->user();

        // Apply warehouse filtering for non-super-admin
        $gudangId = null;
        if (! $user?->isSuperAdmin()) {
            $gudangId = $user?->getCurrentGudang()?->id;
        }

        $penjualans = Penjualan::with('user:id,name')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->latest('tgl_transaksi')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                '__key' => 'penjualan-'.$m->id,
                'id' => $m->id,
                'tanggal' => $m->tgl_transaksi,
                'tipe' => 'Penjualan',
                'nomor' => $m->nomor,
                'pembuat' => $m->user?->name ?? '—',
                'status' => $m->status,
                'total' => (float) ($m->grand_total ?? 0),
            ]);

        $pembelians = Pembelian::with('user:id,name')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->latest('tgl_transaksi')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                '__key' => 'pembelian-'.$m->id,
                'id' => $m->id,
                'tanggal' => $m->tgl_transaksi,
                'tipe' => 'Pembelian',
                'nomor' => $m->nomor,
                'pembuat' => $m->user?->name ?? '—',
                'status' => $m->status,
                'total' => (float) ($m->grand_total ?? 0),
            ]);

        $biayas = Biaya::with('user:id,name')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->latest('tgl_transaksi')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                '__key' => 'biaya-'.$m->id,
                'id' => $m->id,
                'tanggal' => $m->tgl_transaksi,
                'tipe' => 'Biaya',
                'nomor' => $m->nomor,
                'pembuat' => $m->user?->name ?? '—',
                'status' => $m->status,
                'total' => (float) ($m->grand_total ?? 0),
            ]);

        $kunjungans = Kunjungan::with('user:id,name')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->latest('tgl_kunjungan')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                '__key' => 'kunjungan-'.$m->id,
                'id' => $m->id,
                'tanggal' => $m->tgl_kunjungan,
                'tipe' => 'Kunjungan',
                'nomor' => $m->nomor,
                'pembuat' => $m->user?->name ?? '—',
                'status' => $m->status,
                'total' => 0,
            ]);

        $pembayarans = Pembayaran::with('user:id,name')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->latest('tgl_pembayaran')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                '__key' => 'pembayaran-'.$m->id,
                'id' => $m->id,
                'tanggal' => $m->tgl_pembayaran,
                'tipe' => 'Pembayaran',
                'nomor' => $m->nomor,
                'pembuat' => $m->user?->name ?? '—',
                'status' => $m->status,
                'total' => (float) ($m->jumlah_bayar ?? 0),
            ]);

        return $penjualans
            ->concat($pembelians)
            ->concat($biayas)
            ->concat($kunjungans)
            ->concat($pembayarans)
            ->sortByDesc('tanggal')
            ->take($limit)
            ->values();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Penjualan' => 'success',
                        'Pembelian' => 'warning',
                        'Biaya' => 'danger',
                        'Kunjungan' => 'info',
                        'Pembayaran' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('pembuat')
                    ->label('Pembuat'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'primary',
                        'Lunas' => 'success',
                        'Canceled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => format_rupiah($state))
                    ->alignRight()
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->recordUrl(function (array $record): string {
                return match ($record['tipe']) {
                    'Penjualan' => PenjualanResource::getUrl('view', ['record' => $record['id']]),
                    'Pembelian' => PembelianResource::getUrl('view', ['record' => $record['id']]),
                    'Biaya' => BiayaResource::getUrl('view', ['record' => $record['id']]),
                    'Kunjungan' => KunjunganResource::getUrl('view', ['record' => $record['id']]),
                    'Pembayaran' => PembayaranResource::getUrl('view', ['record' => $record['id']]),
                    default => '#',
                };
            });
    }
}
