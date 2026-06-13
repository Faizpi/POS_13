<?php

namespace App\Filament\Resources\Kunjungans\Schemas;

use App\Models\GudangProduk;
use App\Models\Kontak;
use App\Models\Produk;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KunjunganForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kunjungan')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_nomor')
                            ->label('No. Kunjungan (Preview)')
                            ->content(function () {
                                $countToday = \App\Models\Kunjungan::where('user_id', auth()->id())
                                    ->whereDate('created_at', \Carbon\Carbon::today())
                                    ->count();
                                return \App\Models\Kunjungan::generateNomor(auth()->id(), $countToday + 1, \Carbon\Carbon::now()) . ' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold'])
                            ->columnSpanFull(),

                        Select::make('kontak_id')
                            ->label('Kontak')
                            ->required()
                            ->searchable()
                            ->options(function () {
                                $user = auth()->user();
                                if ($user?->isSuperAdmin()) {
                                    return Kontak::orderBy('nama')->pluck('nama', 'id');
                                }
                                if ($user?->role === 'user') {
                                    return Kontak::where('created_by', $user->id)
                                        ->orderBy('nama')->pluck('nama', 'id');
                                }
                                $gudangId = $user?->getCurrentGudang()?->id;
                                return Kontak::where(function ($q) use ($gudangId) {
                                    $q->whereNull('gudang_id');
                                    if ($gudangId) {
                                        $q->orWhere('gudang_id', $gudangId);
                                    }
                                })->orderBy('nama')->pluck('nama', 'id');
                            })
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (! $state) {
                                    return;
                                }
                                $kontak = Kontak::find($state);
                                if ($kontak) {
                                    $set('sales_nama', $kontak->nama);
                                    $set('sales_no_telepon', $kontak->no_telp);
                                    $set('sales_alamat', $kontak->alamat);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Pelanggan wajib diisi.',
                                'exists' => 'Pelanggan tidak valid.',
                            ])
                            ->suffixAction(
                                Action::make('scan_kontak')
                                    ->icon('heroicon-o-camera')
                                    ->label('Scan QR')
                                    ->tooltip('Scan QR Code Kontak')
                                    ->extraAttributes([
                                        'x-on:click' => 'event.preventDefault(); if(window.openPosScannerForField) openPosScannerForField($event, "kontak", "Scan Kode Kontak", "id")',
                                    ])
                                    ->action(function () {
                                        // Handled by JS
                                    })
                            ),

                        DatePicker::make('tgl_kunjungan')
                            ->label('Tgl Kunjungan')
                            ->required()
                            ->default(now()),

                        Select::make('tujuan')
                            ->label('Tujuan')
                            ->required()
                            ->options([
                                'Pemeriksaan Stock' => 'Pemeriksaan Stock',
                                'Penagihan' => 'Penagihan',
                                'Penawaran' => 'Penawaran',
                                'Promo Gratis' => 'Promo Gratis',
                                'Promo Sample' => 'Promo Sample',
                            ])
                            ->live()
                            ->native(false)
                            ->validationMessages([
                                'required' => 'Tujuan kunjungan wajib diisi.',
                                'in' => 'Pilih tujuan yang valid.',
                            ])
                            ->columnSpan(2),

                        TextInput::make('sales_nama')
                            ->label('Nama Sales')
                            ->default(fn () => auth()->user()?->name)
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('sales_no_telepon')
                            ->label('No. Telepon Sales')
                            ->tel()
                            ->default(fn () => auth()->user()?->no_telp)
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('sales_alamat')
                            ->label('Alamat Sales')
                            ->rows(2)
                            ->default(fn () => auth()->user()?->alamat)
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),

                        TextInput::make('koordinat')
                            ->label('Koordinat Lokasi')
                            ->extraInputAttributes(['readonly' => true])
                            ->placeholder('Mengambil lokasi GPS...')
                            ->readOnly()
                            ->helperText('Otomatis terisi saat halaman dimuat. Klik ikon peta untuk refresh.')
                            ->suffixActions([
                                Action::make('refresh_gps_kunjungan')
                                    ->icon('heroicon-o-map-pin')
                                    ->label('')
                                    ->tooltip('Refresh lokasi GPS')
                                    ->extraAttributes([
                                        'onclick' => 'event.preventDefault(); if(window.posAutoFillKoordinat) posAutoFillKoordinat();',
                                    ]),

                                Action::make('open_maps_kunjungan')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->label('')
                                    ->tooltip('Buka di Google Maps')
                                    ->url(fn ($get) => $get('koordinat')
                                        ? 'https://www.google.com/maps?q='.urlencode($get('koordinat'))
                                        : '#')
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Item Kunjungan')
                    ->icon('heroicon-o-list-bullet')
                    ->visible(fn (callable $get) => in_array($get('tujuan'), ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample']))
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Select::make('produk_id')
                                    ->label('Produk')
                                    ->required(fn (callable $get) => in_array($get('../../tujuan'), ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample']))
                                    ->options(fn () => Produk::pluck('nama_produk', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->validationMessages([
                                        'required' => 'Pilih produk yang valid.',
                                        'exists' => 'Produk tidak ditemukan.',
                                    ])
                                    ->helperText(function ($state, callable $get) {
                                        if (! $state) {
                                            return null;
                                        }
                                        $tujuan = $get('../../tujuan');
                                        if (! in_array($tujuan, ['Promo Gratis', 'Promo Sample'])) {
                                            return null;
                                        }

                                        $user = auth()->user();
                                        $gudangId = $get('../../gudang_id') ?? $user?->getCurrentGudang()?->id;
                                        if (! $gudangId) {
                                            return null;
                                        }

                                        $stokField = $tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
                                        $stokLabel = $tujuan === 'Promo Gratis' ? 'Stok Gratis' : 'Stok Sample';

                                        $available = GudangProduk::where('gudang_id', $gudangId)
                                            ->where('produk_id', $state)
                                            ->value($stokField) ?? 0;

                                        return "{$stokLabel}: {$available}";
                                    })
                                    ->suffixAction(
                                        Action::make('scan_produk_kunjungan')
                                            ->icon('heroicon-o-camera')
                                            ->label('Scan')
                                            ->tooltip('Scan Barcode Produk')
                                            ->extraAttributes([
                                                'x-on:click' => 'event.preventDefault(); if(window.openPosScannerForField) openPosScannerForField($event, "produk", "Scan Kode Produk", "id")',
                                            ])
                                            ->action(function () {
                                                // Handled by JS
                                            })
                                    )
                                    ->columnSpan(2),

                                TextInput::make('jumlah')
                                    ->label('Qty')
                                    ->required(fn (callable $get) => in_array($get('../../tujuan'), ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample']))
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->validationMessages([
                                        'required' => 'Qty wajib diisi.',
                                        'numeric' => 'Qty harus berupa angka.',
                                        'min' => 'Qty minimal 1.',
                                    ])
                                    ->rules([
                                        fn (callable $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $tujuan = $get('../../tujuan');
                                            if (! in_array($tujuan, ['Promo Gratis', 'Promo Sample'])) {
                                                return;
                                            }

                                            $produkId = $get('produk_id');
                                            if (! $produkId) {
                                                return;
                                            }

                                            $user = auth()->user();
                                            $gudangId = $get('../../gudang_id') ?? $user?->getCurrentGudang()?->id;
                                            if (! $gudangId) {
                                                return;
                                            }

                                            $stokField = $tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
                                            $stokLabel = $tujuan === 'Promo Gratis' ? 'stok gratis' : 'stok sample';

                                            $available = GudangProduk::where('gudang_id', $gudangId)
                                                ->where('produk_id', $produkId)
                                                ->value($stokField) ?? 0;

                                            if ($value > $available) {
                                                $fail("Qty melebihi {$stokLabel} yang tersedia ({$available}).");
                                            }
                                        },
                                    ]),

                                TextInput::make('batch_number')->label('Batch'),

                                DatePicker::make('expired_date')->label('Exp'),

                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1),
                    ]),

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Textarea::make('memo')->rows(3)->columnSpanFull(),
                        FileUpload::make('lampiran_paths')
                            ->multiple()
                            ->disk('public')
                            ->directory('lampiran_kunjungan')
                            ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = \App\Models\Kunjungan::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "VST-{$now->format('Ymd')}-{$user->id}-" . str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }
                                return "{$nomor}-" . time() . ".{$file->extension()}";
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
