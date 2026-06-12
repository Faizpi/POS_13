<?php

namespace App\Filament\Resources\Penjualans\Schemas;

use App\Models\GudangProduk;
use App\Models\Gudang;
use App\Models\Kontak;
use App\Models\Produk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenjualanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pelanggan')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Select::make('pelanggan')
                            ->label('Pelanggan')
                            ->required()
                            ->searchable()
                            // Gap 2 fix: filter kontak berdasarkan gudang aktif user (sesuai legacy)
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                if ($user?->isSuperAdmin()) {
                                    return Kontak::orderBy('nama')->pluck('nama', 'nama');
                                }
                                if ($user?->role === 'user') {
                                    return Kontak::where('created_by', $user->id)
                                        ->orderBy('nama')->pluck('nama', 'nama');
                                }
                                $gudangId = $get('gudang_id') ?? $user?->getCurrentGudang()?->id;
                                return Kontak::where(function ($q) use ($gudangId) {
                                    $q->whereNull('gudang_id');
                                    if ($gudangId) {
                                        $q->orWhere('gudang_id', $gudangId);
                                    }
                                })->orderBy('nama')->pluck('nama', 'nama');
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $kontak = Kontak::where('nama', $state)->first();
                                    if ($kontak) {
                                        $set('no_telepon', $kontak->no_telp);
                                        $set('alamat_penagihan', $kontak->alamat);
                                    }
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('nama')->label('Nama')->required(),
                                TextInput::make('no_telp')->label('No. Telepon')->tel(),
                                Textarea::make('alamat')->label('Alamat')->rows(2),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $kontak = Kontak::create([
                                    'nama'      => $data['nama'],
                                    'no_telp'   => $data['no_telp'] ?? null,
                                    'alamat'    => $data['alamat'] ?? null,
                                    'created_by' => auth()->id(),
                                    'gudang_id' => auth()->user()?->getCurrentGudang()?->id,
                                ]);

                                return $kontak->nama;
                            })
                            ->suffixAction(
                                Action::make('scan_kontak_penjualan')
                                    ->icon('heroicon-o-camera')
                                    ->label('')
                                    ->tooltip('Scan QR Code Kontak')
                                    ->extraAttributes([
                                        'x-on:click' => 'event.preventDefault(); if(window.openPosScannerForField) openPosScannerForField($event, "kontak", "Scan Kode Kontak", "nama")',
                                    ])
                            ),

                        TextInput::make('no_telepon')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20),

                        Textarea::make('alamat_penagihan')
                            ->label('Alamat Penagihan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Detail Transaksi')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = \App\Models\Penjualan::where('user_id', auth()->id())
                                    ->whereDate('created_at', \Carbon\Carbon::today())
                                    ->count();
                                return \App\Models\Penjualan::generateNomor(auth()->id(), $countToday + 1, \Carbon\Carbon::now()) . ' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        DatePicker::make('tgl_transaksi')
                            ->label('Tgl Transaksi')
                            ->required()
                            ->default(now())
                            ->disabled(fn () => auth()->user()?->role === 'user' && request()->routeIs('*.create'))
                            ->dehydrated(),

                        Select::make('syarat_pembayaran')
                            ->label('Syarat Pembayaran')
                            ->required()
                            ->options([
                                'Cash'   => 'Cash',
                                'Net 7'  => 'Net 7 Days',
                                'Net 14' => 'Net 14 Days',
                                'Net 30' => 'Net 30 Days',
                                'Net 60' => 'Net 60 Days',
                            ])
                            ->default('Cash')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $tgl = $get('tgl_transaksi');
                                if ($state === 'Cash' || ! $tgl) {
                                    $set('tgl_jatuh_tempo', null);

                                    return;
                                }
                                $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
                                if (isset($days[$state])) {
                                    $set('tgl_jatuh_tempo', Carbon::parse($tgl)->addDays($days[$state])->format('Y-m-d'));
                                }
                            }),

                        DatePicker::make('tgl_jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->disabled()
                            ->dehydrated(),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->required()
                            ->options(function () {
                                $user = auth()->user();
                                if (! $user) {
                                    return [];
                                }
                                if ($user->isSuperAdmin()) {
                                    return Gudang::pluck('nama_gudang', 'id');
                                }
                                if ($user->role === 'admin') {
                                    return $user->gudangs->pluck('nama_gudang', 'id');
                                }
                                if ($user->role === 'spectator') {
                                    return $user->spectatorGudangs->pluck('nama_gudang', 'id');
                                }

                                return $user->gudang_id ? [$user->gudang_id => $user->gudang?->nama_gudang] : [];
                            })
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id ?? auth()->user()?->gudang_id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            // Gap 1 fix: saat gudang berubah, refresh daftar produk
                            ->live(),

                        ToggleButtons::make('tipe_harga')
                            ->label('Tipe Harga')
                            ->options([
                                'retail' => 'Retail',
                                'grosir' => 'Grosir',
                            ])
                            ->default('retail')
                            ->inline()
                            ->live()
                            ->required(),

                        TextInput::make('no_referensi')
                            ->label('No. Referensi Pelanggan')
                            ->maxLength(255),

                        TextInput::make('koordinat')
                            ->label('Koordinat Lokasi')
                            ->extraInputAttributes(['readonly' => true])
                            ->placeholder('Mengambil lokasi GPS...')
                            ->readOnly()
                            ->helperText('Otomatis terisi saat halaman dimuat')
                            ->suffixActions([
                                Action::make('refresh_gps_penjualan')
                                    ->icon('heroicon-o-map-pin')
                                    ->label('')
                                    ->tooltip('Refresh lokasi GPS')
                                    ->extraAttributes([
                                        'onclick' => 'event.preventDefault(); if(window.posAutoFillKoordinat) posAutoFillKoordinat();',
                                    ]),
                                Action::make('open_maps_penjualan')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->label('')
                                    ->tooltip('Buka di Google Maps')
                                    ->url(fn ($get) => $get('koordinat')
                                        ? 'https://www.google.com/maps?q=' . urlencode($get('koordinat'))
                                        : '#')
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Item Penjualan')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Select::make('produk_id')
                                    ->label('Produk')
                                    ->required()
                                    // Gap 1 fix: hanya tampilkan produk yang ada stok di gudang terpilih
                                    ->options(function (callable $get) {
                                        $gudangId  = $get('../../gudang_id')
                                            ?? auth()->user()?->getCurrentGudang()?->id
                                            ?? auth()->user()?->gudang_id;

                                        if (! $gudangId) {
                                            return Produk::orderBy('nama_produk')->pluck('nama_produk', 'id');
                                        }

                                        // Hanya produk yang ada di gudang tersebut dengan stok_penjualan > 0
                                        return GudangProduk::where('gudang_id', $gudangId)
                                            ->where('stok_penjualan', '>', 0)
                                            ->with('produk')
                                            ->get()
                                            ->mapWithKeys(fn ($gp) => [
                                                $gp->produk_id => $gp->produk?->nama_produk . ' (Stok: ' . $gp->stok_penjualan . ')',
                                            ]);
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if (! $state) {
                                            return;
                                        }
                                        $produk = Produk::find($state);
                                        if (! $produk) {
                                            return;
                                        }
                                        $tipeHarga = $get('../../tipe_harga') ?? 'retail';
                                        $harga = ($tipeHarga === 'grosir' && $produk->harga_grosir > 0) ? $produk->harga_grosir : $produk->harga;
                                        $set('harga_satuan', (float) $harga);
                                        $set('unit', $produk->satuan);
                                        $set('deskripsi', $produk->deskripsi);
                                        self::recalcRow($set, $get);
                                    })
                                    ->suffixAction(
                                        Action::make('scan_produk_penjualan')
                                            ->icon('heroicon-o-camera')
                                            ->label('')
                                            ->tooltip('Scan Barcode Produk')
                                            ->extraAttributes([
                                                'x-on:click' => 'event.preventDefault(); if(window.openPosScannerForField) openPosScannerForField($event, "produk", "Scan Kode Produk", "id")',
                                            ])
                                    )
                                    ->columnSpan(2),

                                TextInput::make('kuantitas')
                                    ->label('Qty')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('unit')
                                    ->label('Unit')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('harga_satuan')
                                    ->label('Harga')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('diskon')
                                    ->label('Disc %')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('diskon_nominal')
                                    ->label('Disc Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('batch_number')
                                    ->label('Batch'),

                                DatePicker::make('expired_date')
                                    ->label('Exp'),

                                Textarea::make('deskripsi')
                                    ->label('Deskripsi')
                                    ->rows(1)
                                    ->columnSpanFull(),

                                TextInput::make('jumlah_baris')
                                    ->label('Jumlah')
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('Rp')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['produk_id']) && $state['produk_id']
                                        ? Produk::find($state['produk_id'])?->nama_produk.' × '.($state['kuantitas'] ?? 0)
                                        : null)
                            ->addActionLabel('Tambah Item')
                            ->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => self::recalcGrandTotal($set, $get))
                            ->required()
                            ->minItems(1),
                    ]),

                Section::make('Total & Pajak')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextInput::make('diskon_akhir')
                                    ->label('Diskon Akhir')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcGrandTotal($set, $get)),

                        TextInput::make('tax_percentage')
                                    ->label('Pajak')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcGrandTotal($set, $get)),

                        TextInput::make('grand_total')
                                    ->label('Grand Total')
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('Rp')
                                    ->extraInputAttributes(['class' => 'text-2xl font-bold text-primary-600']),
                    ])
                    ->columns(3),

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        TextInput::make('tag')
                                    ->label('Tag (Sales)')
                                    ->default(fn () => auth()->user()?->name)
                                    ->disabled()
                                    ->dehydrated(),

                        Textarea::make('memo')
                                    ->label('Memo')
                                    ->rows(3)
                                    ->columnSpanFull(),

                        FileUpload::make('lampiran_paths')
                                    ->label('Lampiran')
                                    ->multiple()
                                    ->directory('lampiran_penjualan')
                                    ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, $get, $record): string {
                                        $user = auth()->user();
                                        $now = now();

                                        if ($record && $record->exists) {
                                            // Mode Edit — nomor sudah ada
                                            $nomor = $record->nomor;
                                        } else {
                                            // Mode Create — prediksi nomor berdasarkan counter harian
                                            $countToday = \App\Models\Penjualan::where('user_id', $user->id)
                                                ->whereDate('created_at', $now)
                                                ->count();
                                            $noUrut = str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                            $nomor = "INV-{$now->format('Ymd')}-{$user->id}-{$noUrut}";
                                        }

                                        return "{$nomor}-" . time() . ".{$file->extension()}";
                                    })
                                    ->acceptedFileTypes(['image/*', 'application/pdf', 'application/zip', 'application/msword'])
                                    ->maxSize(5120)
                                    ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Recalculate single item row total.
     */
    protected static function recalcRow(callable $set, callable $get): void
    {
        $qty = (float) ($get('kuantitas') ?? 0);
        $harga = (float) ($get('harga_satuan') ?? 0);
        $disc = max(0, min(100, (float) ($get('diskon') ?? 0)));
        $discNominal = (float) ($get('diskon_nominal') ?? 0);

        $gross = $qty * $harga;
        $total = max(0, ($gross * (1 - $disc / 100)) - $discNominal);

        $set('jumlah_baris', round($total, 2));
    }

    /**
     * Recalculate grand total from all items.
     */
    protected static function recalcGrandTotal(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['kuantitas'] ?? 0);
            $harga = (float) ($item['harga_satuan'] ?? 0);
            $disc = max(0, min(100, (float) ($item['diskon'] ?? 0)));
            $discNom = (float) ($item['diskon_nominal'] ?? 0);
            $subtotal += max(0, ($qty * $harga * (1 - $disc / 100)) - $discNom);
        }

        $diskonAkhir = (float) ($get('diskon_akhir') ?? 0);
        $pajak = (float) ($get('tax_percentage') ?? 0);
        $kenaPajak = max(0, $subtotal - $diskonAkhir);
        $total = $kenaPajak + ($kenaPajak * $pajak / 100);

        $set('grand_total', round($total, 2));
    }
}
