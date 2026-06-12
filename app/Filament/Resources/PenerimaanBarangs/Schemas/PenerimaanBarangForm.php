<?php

namespace App\Filament\Resources\PenerimaanBarangs\Schemas;

use App\Models\Gudang;
use App\Models\Pembelian;
use App\Models\PenerimaanBarangItem;
use App\Models\Produk;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenerimaanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Penerimaan')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = \App\Models\PenerimaanBarang::where('user_id', auth()->id())
                                    ->whereDate('created_at', \Carbon\Carbon::today())
                                    ->count();
                                return \App\Models\PenerimaanBarang::generateNomor(auth()->id(), $countToday + 1, \Carbon\Carbon::now()) . ' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->required()
                            ->options(fn() => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn() => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn() => !auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->live(),

                        // Gap 4 fix: multi-select pembelian_ids (legacy: pembelian_ids[])
                        Select::make('pembelian_ids')
                            ->label('Purchase Order (PO)')
                            ->helperText('Pilih satu atau lebih PO. Item akan otomatis terisi dari PO yang dipilih.')
                            ->required()
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (callable $get) {
                                $gudangId = $get('gudang_id');
                                if (!$gudangId) return [];

                                return Pembelian::where('gudang_id', $gudangId)
                                    ->whereIn('status', ['Approved', 'Pending'])
                                    ->get()
                                    ->filter(function ($pembelian) {
                                        // Hanya tampilkan PO yang masih ada item belum diterima
                                        foreach ($pembelian->items as $item) {
                                            $qtyDiterima = PenerimaanBarangItem::whereHas('penerimaanBarang', function ($q) use ($pembelian) {
                                                $q->where('pembelian_id', $pembelian->id)->where('status', 'Approved');
                                            })->where('produk_id', $item->produk_id)->sum('qty_diterima');

                                            $qtySisa = ($item->kuantitas ?? $item->jumlah ?? 0) - $qtyDiterima;
                                            if ($qtySisa > 0) return true;
                                        }
                                        return false;
                                    })
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->nomor . ($p->kontak ? ' — ' . $p->kontak->nama : ''),
                                    ]);
                            })
                            ->live()
                            // Auto-fill items dari PO yang dipilih (sesuai legacy getPembelianDetail)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (empty($state)) {
                                    $set('items', []);
                                    return;
                                }

                                $newItems = [];
                                foreach ((array) $state as $pembelianId) {
                                    $pembelian = Pembelian::with('items.produk')->find($pembelianId);
                                    if (!$pembelian) continue;

                                    foreach ($pembelian->items as $item) {
                                        // Hitung qty yang sudah diterima (approved only)
                                        $qtyDiterima = PenerimaanBarangItem::whereHas('penerimaanBarang', function ($q) use ($pembelianId) {
                                            $q->where('pembelian_id', $pembelianId)->where('status', 'Approved');
                                        })->where('produk_id', $item->produk_id)->sum('qty_diterima');

                                        $qtyPesan = $item->kuantitas ?? $item->jumlah ?? 0;
                                        $qtySisa  = max(0, $qtyPesan - $qtyDiterima);

                                        if ($qtySisa <= 0) continue; // Skip item yang sudah penuh diterima

                                        $newItems[] = [
                                            'pembelian_id'  => $pembelianId,
                                            'produk_id'     => $item->produk_id,
                                            'qty_diterima'  => $qtySisa, // Pre-fill dengan qty sisa
                                            'qty_reject'    => 0,
                                            'tipe_stok'     => 'penjualan',
                                            'batch_number'  => null,
                                            'expired_date'  => null,
                                            'keterangan'    => null,
                                        ];
                                    }
                                }

                                $set('items', $newItems);
                            })
                            ->columnSpanFull(),

                        DatePicker::make('tgl_penerimaan')
                            ->label('Tgl Penerimaan')
                            ->required()
                            ->default(now()),

                        TextInput::make('no_surat_jalan')
                            ->label('No. Surat Jalan')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Section::make('Item Penerimaan')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Item otomatis terisi dari PO yang dipilih. Sesuaikan qty_diterima dan qty_reject.')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                // Track dari PO mana item ini berasal (hidden, penting untuk multi-PO)
                                Hidden::make('pembelian_id'),

                                Select::make('produk_id')
                                    ->label('Produk')
                                    ->required()
                                    ->options(fn() => Produk::orderBy('nama_produk')->pluck('nama_produk', 'id'))
                                    ->searchable()
                                    ->disabled() // Auto-filled dari PO, tidak boleh diubah manual
                                    ->dehydrated()
                                    ->columnSpan(2),

                                TextInput::make('qty_diterima')
                                    ->label('Qty Diterima')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                TextInput::make('qty_reject')
                                    ->label('Qty Reject')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                Select::make('tipe_stok')
                                    ->label('Tipe Stok')
                                    ->options([
                                        'penjualan' => 'Penjualan',
                                        'gratis'    => 'Gratis',
                                        'sample'    => 'Sample',
                                    ])
                                    ->default('penjualan')
                                    ->native(false),

                                TextInput::make('batch_number')->label('Batch'),
                                DatePicker::make('expired_date')->label('Exp'),
                                TextInput::make('keterangan')->label('Keterangan')->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addable(false)   // Tambah item hanya via pilih PO
                            ->deletable(true)  // Bisa hapus item tertentu jika tidak perlu diterima
                            ->reorderableWithButtons()
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['produk_id'])) return null;
                                $produk = Produk::find($state['produk_id']);
                                $qty    = $state['qty_diterima'] ?? 0;
                                return $produk?->nama_produk . ' × ' . $qty;
                            })
                            ->required()
                            ->minItems(1),
                    ]),

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Textarea::make('keterangan')->rows(3)->columnSpanFull(),
                        FileUpload::make('lampiran_paths')
                            ->multiple()
                            ->directory('lampiran_penerimaan')
                            ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = \App\Models\PenerimaanBarang::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "RCV-{$now->format('Ymd')}-{$user->id}-" . str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }
                                return "{$nomor}-" . time() . ".{$file->extension()}";
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
