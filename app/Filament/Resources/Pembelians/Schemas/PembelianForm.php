<?php

namespace App\Filament\Resources\Pembelians\Schemas;

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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PembelianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Pembelian')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = \App\Models\Pembelian::where('user_id', auth()->id())
                                    ->whereDate('created_at', \Carbon\Carbon::today())
                                    ->count();
                                return \App\Models\Pembelian::generateNomor(auth()->id(), $countToday + 1, \Carbon\Carbon::now()) . ' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        DatePicker::make('tgl_transaksi')
                            ->label('Tgl Transaksi')
                            ->required()
                            ->default(now()),

                        Select::make('syarat_pembayaran')
                            ->label('Syarat Pembayaran')
                            ->required()
                            ->options([
                                'Cash' => 'Cash',
                                'Net 7' => 'Net 7 Hari',
                                'Net 14' => 'Net 14 Hari',
                                'Net 30' => 'Net 30 Hari',
                                'Net 60' => 'Net 60 Hari',
                            ])
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

                        Select::make('tipe_harga')
                            ->label('Tipe Harga')
                            ->required()
                            ->options([
                                'retail' => 'Retail',
                                'grosir' => 'Grosir',
                            ])
                            ->default('retail')
                            ->native(false),

                        TextInput::make('no_referensi')
                            ->label('No Referensi')
                            ->maxLength(255),

                        TextInput::make('no_resi')
                            ->label('Nomor Resi')
                            ->maxLength(255),

                        TextInput::make('biaya_pengiriman')
                            ->label('Biaya Pengiriman')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $get) => self::recalcGrandTotal($set, $get)),

                        Select::make('urgensi')
                            ->label('Urgensi')
                            ->required()
                            ->options([
                                'Rendah' => 'Rendah',
                                'Sedang' => 'Sedang',
                                'Tinggi' => 'Tinggi',
                            ])
                            ->native(false),

                        Select::make('kontak_id')
                            ->label('Supplier')
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                if ($user?->isSuperAdmin()) {
                                    return Kontak::orderBy('nama')->pluck('nama', 'id');
                                }
                                if ($user?->role === 'user') {
                                    return Kontak::where('created_by', $user->id)
                                        ->orderBy('nama')->pluck('nama', 'id');
                                }
                                $gudangId = $get('gudang_id') ?? $user?->getCurrentGudang()?->id;
                                return Kontak::where(function ($q) use ($gudangId) {
                                    $q->whereNull('gudang_id');
                                    if ($gudangId) {
                                        $q->orWhere('gudang_id', $gudangId);
                                    }
                                })->orderBy('nama')->pluck('nama', 'id');
                            })
                            ->searchable()
                            ->preload(),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->required()
                            ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->live(),

                        TextInput::make('tahun_anggaran')
                            ->label('Tahun Anggaran'),

                        TextInput::make('tag')
                            ->label('Tag (Sales)')
                            ->default(fn () => auth()->user()?->name)
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('staf_penyetuju')
                            ->label('Staf Penyetuju')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Diisi otomatis berdasarkan gudang'),

                        TextInput::make('koordinat')
                            ->label('Koordinat Lokasi')
                            ->extraInputAttributes(['readonly' => true])
                            ->placeholder('Mengambil lokasi GPS...')
                            ->readOnly()
                            ->helperText('Otomatis terisi saat halaman dimuat')
                            ->suffixActions([
                                Action::make('refresh_gps_pembelian')
                                    ->icon('heroicon-o-map-pin')
                                    ->label('')
                                    ->tooltip('Refresh lokasi GPS')
                                    ->extraAttributes([
                                        'onclick' => 'event.preventDefault(); if(window.posAutoFillKoordinat) posAutoFillKoordinat();',
                                    ]),
                                Action::make('open_maps_pembelian')
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

                Section::make('Item Pembelian')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Select::make('produk_id')
                                    ->label('Produk')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $user = auth()->user();
                                        if ($user?->isSuperAdmin()) {
                                            return Produk::pluck('nama_produk', 'id');
                                        }
                                        $gudangId = $get('../../gudang_id') ?? $user?->getCurrentGudang()?->id;
                                        if (!$gudangId) {
                                            return [];
                                        }
                                        return Produk::whereHas('stokDiGudang', function ($query) use ($gudangId) {
                                            $query->where('gudang_id', $gudangId);
                                        })->pluck('nama_produk', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $produk = Produk::find($state);
                                        if ($produk) {
                                            $set('kuantitas', 0);
                                            $set('unit', $produk->satuan);
                                            $set('harga_satuan', (float) $produk->harga);
                                        }
                                        self::recalcRow($set, $get);
                                    })
                                    ->suffixAction(
                                        Action::make('scan_produk_pembelian')
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
                                    ->default(0)
                                    ->minValue(1)
                                    ->live()
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
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('diskon')
                                    ->label('Diskon %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcRow($set, $get)),

                                TextInput::make('jumlah_baris')
                                    ->label('Jumlah')
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('Rp')
                                    ->columnSpanFull(),

                                Textarea::make('deskripsi')
                                    ->label('Deskripsi')
                                    ->rows(1)
                                    ->columnSpanFull(),

                                TextInput::make('batch_number')->label('Batch'),
                                DatePicker::make('expired_date')->label('Exp'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible()
                            ->reorderableWithButtons()
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
                        Textarea::make('memo')
                            ->label('Memo')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('lampiran_paths')
                            ->label('Lampiran')
                            ->multiple()
                            ->disk('public')
                            ->directory('lampiran_pembelian')
                            ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = \App\Models\Pembelian::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "PR-{$now->format('Ymd')}-{$user->id}-" . str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }
                                return "{$nomor}-" . time() . ".{$file->extension()}";
                            })
                            ->acceptedFileTypes(['image/*', 'application/pdf', 'application/zip', 'application/msword'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function recalcRow(callable $set, callable $get): void
    {
        $qty = (float) ($get('kuantitas') ?? 0);
        $harga = (float) ($get('harga_satuan') ?? 0);
        $disc = max(0, min(100, (float) ($get('diskon') ?? 0)));

        $total = max(0, ($qty * $harga) * (1 - $disc / 100));
        $set('jumlah_baris', round($total, 2));
    }

    protected static function recalcGrandTotal(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['kuantitas'] ?? 0);
            $harga = (float) ($item['harga_satuan'] ?? 0);
            $disc = max(0, min(100, (float) ($item['diskon'] ?? 0)));
            $subtotal += max(0, ($qty * $harga) * (1 - $disc / 100));
        }

        $diskonAkhir = (float) ($get('diskon_akhir') ?? 0);
        $pajak = (float) ($get('tax_percentage') ?? 0);
        $biayaPengiriman = (float) ($get('biaya_pengiriman') ?? 0);
        $kenaPajak = max(0, $subtotal - $diskonAkhir);
        $total = $kenaPajak + ($kenaPajak * $pajak / 100) + $biayaPengiriman;

        $set('grand_total', round($total, 2));
    }
}
