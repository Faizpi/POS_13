<?php

namespace App\Filament\Resources\PenerimaanBarangs\Schemas;

use App\Exports\PenerimaanBarangTemplateExport;
use App\Imports\PenerimaanBarangItemImport;
use App\Models\Gudang;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Produk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class PenerimaanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Penerimaan')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Placeholder::make('preview_nomor')
                            ->label('No Transaksi (Preview)')
                            ->content(function () {
                                $countToday = PenerimaanBarang::where('user_id', auth()->id())
                                    ->whereDate('created_at', Carbon::today())
                                    ->count();

                                return PenerimaanBarang::generateNomor(auth()->id(), $countToday + 1, Carbon::now()).' (Auto)';
                            })
                            ->hiddenOn(['view', 'edit'])
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->required()
                            ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
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
                                if (! $gudangId) {
                                    return [];
                                }

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
                                            if ($qtySisa > 0) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    })
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->nomor.($p->kontak ? ' — '.$p->kontak->nama : ''),
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
                                    if (! $pembelian) {
                                        continue;
                                    }

                                    foreach ($pembelian->items as $item) {
                                        // Hitung qty yang sudah diterima (approved only)
                                        $qtyDiterima = PenerimaanBarangItem::whereHas('penerimaanBarang', function ($q) use ($pembelianId) {
                                            $q->where('pembelian_id', $pembelianId)->where('status', 'Approved');
                                        })->where('produk_id', $item->produk_id)->sum('qty_diterima');

                                        $qtyPesan = $item->kuantitas ?? $item->jumlah ?? 0;
                                        $qtySisa = max(0, $qtyPesan - $qtyDiterima);

                                        if ($qtySisa <= 0) {
                                            continue;
                                        } // Skip item yang sudah penuh diterima

                                        $newItems[] = [
                                            'pembelian_id' => $pembelianId,
                                            'produk_id' => $item->produk_id,
                                            'qty_diterima' => $qtySisa, // Pre-fill dengan qty sisa
                                            'qty_reject' => 0,
                                            'tipe_stok' => 'penjualan',
                                            'batch_number' => null,
                                            'expired_date' => null,
                                            'keterangan' => null,
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
                    ->columns(['default' => 2])
                    ->columnSpanFull(),

                Section::make('Item Penerimaan')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Item otomatis terisi dari PO yang dipilih. Sesuaikan qty_diterima dan qty_reject.')
                    ->headerActions([
                        Action::make('import_excel')
                            ->label('Import Excel')
                            ->icon('heroicon-o-arrow-up-on-square')
                            ->color('success')
                            ->form([
                                FileUpload::make('file')
                                    ->label('Pilih File Excel')
                                    ->acceptedFileTypes([
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.ms-excel',
                                    ])
                                    ->required(),
                            ])
                            ->action(function (array $data, callable $set, callable $get) {
                                $file = $data['file'] ?? null;
                                if (! $file) {
                                    return;
                                }

                                $path = $file instanceof TemporaryUploadedFile
                                    ? $file->getRealPath()
                                    : $file;

                                $import = new PenerimaanBarangItemImport;

                                try {
                                    Excel::import($import, $path);
                                } catch (ValidationException $e) {
                                    $errorMessages = collect($e->failures())
                                        ->map(fn ($failure) => "Baris {$failure->row()}: ".implode(', ', $failure->errors()))
                                        ->implode("\n");

                                    Notification::make()
                                        ->title('Validasi Gagal')
                                        ->body($errorMessages)
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $existingItems = $get('items') ?? [];
                                $newItems = $import->importedItems->map(fn ($item) => [
                                    'produk_id' => $item['produk_id'],
                                    'qty_diterima' => $item['qty_diterima'],
                                    'batch_number' => $item['batch_number'],
                                    'expired_date' => $item['expired_date'],
                                    'qty_reject' => 0,
                                    'tipe_stok' => 'penjualan',
                                    'keterangan' => null,
                                ])->toArray();

                                $set('items', array_merge($existingItems, $newItems));

                                Notification::make()
                                    ->title('Import Berhasil')
                                    ->body(count($newItems).' item berhasil diimport dari Excel.')
                                    ->success()
                                    ->send();
                            }),
                        Action::make('download_template')
                            ->label('Download Template')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('info')
                            ->action(function () {
                                return response()->streamDownload(function () {
                                    echo Excel::raw(
                                        new PenerimaanBarangTemplateExport,
                                        \Maatwebsite\Excel\Excel::XLSX
                                    );
                                }, 'template-penerimaan-barang.xlsx');
                            }),
                    ])
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                // Track dari PO mana item ini berasal (hidden, penting untuk multi-PO)
                                Hidden::make('pembelian_id'),

                                Select::make('produk_id')
                                    ->label('Produk')
                                    ->required()
                                    ->options(fn () => Produk::orderBy('nama_produk')->pluck('nama_produk', 'id'))
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
                                        'gratis' => 'Gratis',
                                        'sample' => 'Sample',
                                    ])
                                    ->default('penjualan')
                                    ->native(false),

                                TextInput::make('batch_number')->label('Batch'),
                                DatePicker::make('expired_date')->label('Exp'),
                                TextInput::make('keterangan')->label('Keterangan')->columnSpanFull(),
                            ])
                            ->columns(['default' => 3])
                            ->defaultItems(0)
                            ->addable(false)   // Tambah item hanya via pilih PO
                            ->deletable(true)  // Bisa hapus item tertentu jika tidak perlu diterima
                            ->reorderableWithButtons()
                            ->itemLabel(function (array $state): ?string {
                                if (! isset($state['produk_id'])) {
                                    return null;
                                }
                                $produk = Produk::find($state['produk_id']);
                                $qty = $state['qty_diterima'] ?? 0;

                                return $produk?->nama_produk.' × '.$qty;
                            })
                            ->required()
                            ->minItems(1),
                    ])
                    ->columnSpanFull(),

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Textarea::make('keterangan')->rows(3)->columnSpanFull(),
                        FileUpload::make('lampiran_paths')
                            ->multiple()
                            ->disk('public')
                            ->directory('lampiran_penerimaan')
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, $record): string {
                                $user = auth()->user();
                                $now = now();
                                if ($record && $record->exists) {
                                    $nomor = $record->nomor;
                                } else {
                                    $countToday = PenerimaanBarang::where('user_id', $user->id)->whereDate('created_at', $now)->count();
                                    $nomor = "RCV-{$now->format('Ymd')}-{$user->id}-".str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                                }

                                return "{$nomor}-".time().".{$file->extension()}";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
