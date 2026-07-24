<?php

namespace App\Filament\Resources\Pembayarans\Schemas;

use App\Models\Gudang;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PembayaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = Pembayaran::where('user_id', auth()->id())
                                    ->whereDate('created_at', Carbon::today())
                                    ->count();

                                return Pembayaran::generateNomor(auth()->id(), $countToday + 1, Carbon::now()).' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        // Gudang — autofill untuk non-super_admin, pilih untuk super_admin
                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // Gap 3 fix: multi-select penjualan_ids (legacy: penjualan_ids[])
                        Select::make('penjualan_ids')
                            ->label('Invoice Penjualan')
                            ->helperText('Bisa pilih beberapa invoice sekaligus. Pembayaran akan didistribusikan otomatis.')
                            ->required()
                            ->searchable()
                            ->multiple()
                            ->options(function (callable $get) {
                                $gudangId = $get('gudang_id');

                                $query = Penjualan::where('status', 'Approved');
                                if ($gudangId) {
                                    $query->where('gudang_id', $gudangId);
                                }

                                return $query->get()->mapWithKeys(function ($p) {
                                    $sudah = (float) Pembayaran::where('penjualan_id', $p->id)
                                        ->where('status', 'Approved')->sum('jumlah_bayar');
                                    $sisa = max(0, (float) ($p->grand_total ?? 0) - $sudah);
                                    if ($sisa <= 0) {
                                        return [];
                                    }

                                    return [$p->id => $p->nomor.' — '.$p->pelanggan.' (Sisa: '.format_rupiah($sisa).')'];
                                });
                            })
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (empty($state)) {
                                    $set('sisa_hutang_preview', null);
                                    $set('jumlah_bayar', null);

                                    return;
                                }

                                // Hitung total sisa hutang dari SEMUA invoice yang dipilih
                                $totalSisa = 0;
                                foreach ((array) $state as $penjualanId) {
                                    $penjualan = Penjualan::find($penjualanId);
                                    if (! $penjualan) {
                                        continue;
                                    }
                                    $totalBayar = (float) Pembayaran::where('penjualan_id', $penjualanId)
                                        ->where('status', 'Approved')->sum('jumlah_bayar');
                                    $totalSisa += max(0, (float) $penjualan->grand_total - $totalBayar);
                                }

                                $set('sisa_hutang_preview', $totalSisa);
                                $set('jumlah_bayar', $totalSisa);
                            })
                            ->columnSpanFull(),

                        // Preview total sisa hutang dari semua invoice yang dipilih (readonly)
                        TextInput::make('sisa_hutang_preview')
                            ->label('Total Sisa Hutang Invoice Terpilih')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Jumlah total dari semua invoice yang dipilih'),

                        DatePicker::make('tgl_pembayaran')
                            ->label('Tgl Pembayaran')
                            ->required()
                            ->default(now()),

                        Select::make('metode_pembayaran')
                            ->label('Metode Pembayaran')
                            ->required()
                            ->options([
                                'Cash' => 'Cash',
                                'Transfer' => 'Transfer Bank',
                                'Cheque' => 'Cheque',
                                'QRIS' => 'QRIS',
                                'Debit' => 'Debit',
                            ])
                            ->native(false),

                        TextInput::make('jumlah_bayar')
                            ->label('Jumlah Bayar')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->helperText('Akan didistribusikan ke masing-masing invoice secara proporsional'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 2])
                    ->columnSpanFull(),

                Section::make('Bukti Pembayaran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('lampiran_paths')
                            ->label('Lampiran / Bukti Bayar')
                            ->multiple()
                            ->disk('public')
                            ->directory('lampiran_pembayaran')
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = Pembayaran::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "PAY-{$now->format('Ymd')}-{$user->id}-".str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }

                                return "{$nomor}-".time().".{$file->extension()}";
                            })
                            ->image()
                            ->imageEditor()
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
