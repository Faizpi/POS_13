<?php

namespace App\Filament\Pages;

use App\Exports\StokExport;
use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Produk;
use App\Models\StokLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class StokPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Stok Gudang';

    protected static ?string $title = 'Manajemen Stok';

    protected string $view = 'filament.pages.stok';

    // Form state for manual stok update (super_admin)
    public ?int $form_gudang_id = null;
    public ?int $form_produk_id = null;
    public int $form_stok_penjualan = 0;
    public int $form_stok_gratis = 0;
    public int $form_stok_sample = 0;
    public ?string $form_keterangan = null;

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, ['super_admin', 'admin', 'spectator']);
    }

    public function getData(): array
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $gudangs = Gudang::with(['gudangProduks.produk'])->get();
        } elseif ($user->role === 'admin') {
            $gudangIds = $user->gudangs->pluck('id');
            $gudangs = Gudang::with(['gudangProduks.produk'])->whereIn('id', $gudangIds)->get();
        } else {
            $cg = $user?->getCurrentGudang();
            $gudangs = $cg ? Gudang::with(['gudangProduks.produk'])->where('id', $cg->id)->get() : collect();
        }

        // Normalize stok total
        $gudangs->each(function ($g) {
            $g->gudangProduks->each(function ($s) {
                $s->stok = ($s->stok_penjualan ?? 0) + ($s->stok_gratis ?? 0) + ($s->stok_sample ?? 0);
            });
        });

        return [
            'gudangs' => $gudangs,
            'allGudangs' => Gudang::pluck('nama_gudang', 'id'),
            'allProduks' => Produk::orderBy('nama_produk')->pluck('nama_produk', 'id'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $actions = [];

        // Stok log button
        $actions[] = Action::make('stokLog')
            ->label('Riwayat Perubahan')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->url(StokLogPage::getUrl());

        // Export Excel button
        $actions[] = Action::make('exportStok')
            ->label('Export Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('gudang_id')
                    ->label('Pilih Gudang')
                    ->options(fn() => Gudang::pluck('nama_gudang', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),
            ])
            ->action(function (array $data) use ($user) {
                $gudang = Gudang::findOrFail($data['gudang_id']);
                if (!$user->isSuperAdmin() && !$user->canAccessGudang($gudang->id)) {
                    Notification::make()->title('Tidak memiliki akses ke gudang ini.')->danger()->send();
                    return;
                }
                $stokData = GudangProduk::where('gudang_id', $gudang->id)->with('produk')->get();
                return response()->streamDownload(function () use ($gudang, $stokData, $user) {
                    echo \Maatwebsite\Excel\Facades\Excel::raw(
                        new StokExport($gudang, $stokData, $user->name),
                        \Maatwebsite\Excel\Excel::XLSX
                    );
                }, 'Stok_' . str_replace(' ', '_', $gudang->nama_gudang) . '_' . now()->format('Ymd') . '.xlsx');
            })
            ->modalSubmitActionLabel('Export')
            ->modalCancelActionLabel('Batal');

        // Manual update stok (super_admin only)
        if ($user->isSuperAdmin()) {
            $actions[] = Action::make('updateStok')
                ->label('Update Stok')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->form([
                    Select::make('gudang_id')
                        ->label('Gudang')
                        ->options(fn() => Gudang::pluck('nama_gudang', 'id'))
                        ->required()->searchable()->preload(),

                    Select::make('produk_id')
                        ->label('Produk')
                        ->options(fn() => Produk::orderBy('nama_produk')->pluck('nama_produk', 'id'))
                        ->required()->searchable()->preload(),

                    TextInput::make('stok_penjualan')
                        ->label('Stok Penjualan')
                        ->numeric()->required()->default(0)->minValue(0),

                    TextInput::make('stok_gratis')
                        ->label('Stok Gratis')
                        ->numeric()->required()->default(0)->minValue(0),

                    TextInput::make('stok_sample')
                        ->label('Stok Sample')
                        ->numeric()->required()->default(0)->minValue(0),

                    Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->rows(2)
                        ->placeholder('Alasan perubahan stok'),
                ])
                ->action(function (array $data) use ($user) {
                    $produk = Produk::findOrFail($data['produk_id']);
                    $gudang = Gudang::findOrFail($data['gudang_id']);

                    $existing = GudangProduk::where('gudang_id', $data['gudang_id'])
                        ->where('produk_id', $data['produk_id'])->first();

                    $stokSebelum = $existing ? $existing->stok : 0;
                    $newTotal = $data['stok_penjualan'] + $data['stok_gratis'] + $data['stok_sample'];
                    $selisih = $newTotal - $stokSebelum;

                    $gp = GudangProduk::updateOrCreate(
                        ['gudang_id' => $data['gudang_id'], 'produk_id' => $data['produk_id']],
                        [
                            'stok' => $newTotal,
                            'stok_penjualan' => $data['stok_penjualan'],
                            'stok_gratis' => $data['stok_gratis'],
                            'stok_sample' => $data['stok_sample'],
                        ]
                    );

                    if ($selisih !== 0) {
                        StokLog::create([
                            'gudang_produk_id' => $gp->id,
                            'produk_id' => $produk->id,
                            'gudang_id' => $gudang->id,
                            'user_id' => $user->id,
                            'produk_nama' => $produk->nama_produk,
                            'gudang_nama' => $gudang->nama_gudang,
                            'user_nama' => $user->name,
                            'stok_sebelum' => $stokSebelum,
                            'stok_sesudah' => $newTotal,
                            'selisih' => $selisih,
                            'keterangan' => $data['keterangan'] ?? 'Perubahan stok manual via Filament',
                        ]);
                    }

                    Notification::make()
                        ->title('Stok berhasil diperbarui.')
                        ->success()->send();
                })
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal');
        }

        return $actions;
    }
}
