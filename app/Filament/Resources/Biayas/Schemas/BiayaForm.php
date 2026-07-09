<?php

namespace App\Filament\Resources\Biayas\Schemas;

use App\Models\Biaya;
use App\Models\Gudang;
use App\Models\Kontak;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BiayaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Biaya')
                    ->icon('heroicon-o-wallet')
                    ->schema([
                        Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = Biaya::where('user_id', auth()->id())
                                    ->whereDate('created_at', Carbon::today())
                                    ->count();

                                return Biaya::generateNomor(auth()->id(), $countToday + 1, Carbon::now()).' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        ToggleButtons::make('jenis_biaya')
                            ->label('Jenis Biaya')
                            ->options([
                                'masuk' => 'Biaya Masuk',
                                'keluar' => 'Biaya Keluar',
                            ])
                            ->colors([
                                'masuk' => 'success',
                                'keluar' => 'danger',
                            ])
                            ->icons([
                                'masuk' => 'heroicon-o-arrow-down-tray',
                                'keluar' => 'heroicon-o-arrow-up-tray',
                            ])
                            ->default('keluar')
                            ->inline()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->required(),

                        TextInput::make('tag')
                            ->label('Tag (Sales)')
                            ->default(fn () => auth()->user()?->name)
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('bayar_dari')
                            ->label('Bayar Dari')
                            ->required(),

                        TextInput::make('penerima')
                            ->label('Penerima')
                            ->maxLength(255)
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
                            ->suffixAction(
                                Action::make('scan_kontak_biaya')
                                    ->icon('heroicon-o-camera')
                                    ->label('')
                                    ->tooltip('Scan QR Code Penerima')
                                    ->extraAttributes([
                                        'x-on:click' => 'event.preventDefault(); if(window.openPosScannerForField) openPosScannerForField($event, "kontak", "Scan Kode Penerima", "nama")',
                                    ])
                            ),

                        TextInput::make('no_telepon')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20),

                        DatePicker::make('tgl_transaksi')
                            ->label('Tgl Transaksi')
                            ->required()
                            ->default(now()),

                        Select::make('cara_pembayaran')
                            ->label('Cara Pembayaran')
                            ->options([
                                'Cash' => 'Cash',
                                'Transfer' => 'Transfer',
                                'Cheque' => 'Cheque',
                                'QRIS' => 'QRIS',
                            ])
                            ->native(false),

                        TextInput::make('koordinat')
                            ->label('Koordinat Lokasi')
                            ->extraInputAttributes(['readonly' => true])
                            ->placeholder('Mengambil lokasi GPS...')
                            ->readOnly()
                            ->helperText('Otomatis terisi saat halaman dimuat')
                            ->suffixActions([
                                Action::make('refresh_gps_biaya')
                                    ->icon('heroicon-o-map-pin')
                                    ->label('')
                                    ->tooltip('Refresh lokasi GPS')
                                    ->extraAttributes([
                                        'onclick' => 'event.preventDefault(); if(window.posAutoFillKoordinat) posAutoFillKoordinat();',
                                    ]),
                                Action::make('open_maps_biaya')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->label('')
                                    ->tooltip('Buka di Google Maps')
                                    ->url(fn ($get) => $get('koordinat')
                                        ? 'https://www.google.com/maps?q='.urlencode($get('koordinat'))
                                        : '#')
                                    ->openUrlInNewTab(),
                            ]),

                        Textarea::make('alamat_penagihan')
                            ->label('Alamat Penagihan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Item Biaya')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                TextInput::make('kategori')
                                    ->label('Kategori')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('deskripsi')
                                    ->label('Deskripsi')
                                    ->rows(1),

                                TextInput::make('jumlah')
                                    ->label('Jumlah')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $get) => self::recalcGrandTotal($set, $get)),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Item')
                            ->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => self::recalcGrandTotal($set, $get))
                            ->required()
                            ->minItems(1),
                    ]),

                Section::make('Total & Pajak')
                    ->icon('heroicon-o-calculator')
                    ->schema([
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
                    ->columns(2),

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Textarea::make('memo')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('lampiran_paths')
                            ->label('Lampiran')
                            ->multiple()
                            ->disk('public')
                            ->directory('lampiran_biaya')
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = Biaya::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "EXP-{$now->format('Ymd')}-{$user->id}-".str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }

                                return "{$nomor}-".time().".{$file->extension()}";
                            })
                            ->acceptedFileTypes(['image/*', 'application/pdf', 'application/zip'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function recalcGrandTotal(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['jumlah'] ?? 0);
        }

        $pajak = max(0, min(100, (float) ($get('tax_percentage') ?? 0)));
        $total = $subtotal + ($subtotal * $pajak / 100);

        $set('grand_total', round($total, 2));
    }
}
