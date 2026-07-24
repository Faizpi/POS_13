<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Produk;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Opname')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->required()
                            ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                            ->default(fn () => auth()->user()?->getCurrentGudang()?->id)
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->live(),

                        DatePicker::make('tgl_opname')
                            ->label('Tanggal Opname')
                            ->required()
                            ->default(now()),

                        Textarea::make('memo')
                            ->label('Memo')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Item Opname')
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
                                            return Produk::orderBy('nama_produk')->pluck('nama_produk', 'id');
                                        }
                                        $gudangId = $get('../../gudang_id') ?? $user?->getCurrentGudang()?->id;
                                        if (! $gudangId) {
                                            return [];
                                        }

                                        return Produk::whereHas('stokDiGudang', function ($query) use ($gudangId) {
                                            $query->where('gudang_id', $gudangId);
                                        })->orderBy('nama_produk')->pluck('nama_produk', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $gudangId = $get('../../gudang_id') ?? auth()->user()?->getCurrentGudang()?->id;
                                        if ($state && $gudangId) {
                                            $stok = GudangProduk::where('gudang_id', $gudangId)
                                                ->where('produk_id', $state)
                                                ->first();

                                            if ($stok) {
                                                $qtySystem = ($stok->stok_penjualan ?? 0) + ($stok->stok_gratis ?? 0) + ($stok->stok_sample ?? 0);
                                            } else {
                                                $qtySystem = 0;
                                            }

                                            $set('qty_system', $qtySystem);
                                            $set('qty_aktual', 0);
                                            $set('selisih', 0 - $qtySystem);
                                        }
                                    }),

                                TextInput::make('batch_number')
                                    ->label('No Batch')
                                    ->maxLength(255),

                                DatePicker::make('expired_date')
                                    ->label('Exp Date'),

                                TextInput::make('qty_system')
                                    ->label('Qty System')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('qty_aktual')
                                    ->label('Qty Aktual')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $qtySystem = (float) ($get('qty_system') ?? 0);
                                        $qtyAktual = (float) ($state ?? 0);
                                        $set('selisih', $qtyAktual - $qtySystem);
                                    }),

                                TextInput::make('selisih')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->itemLabel(fn (array $state): ?string => isset($state['produk_id']) && $state['produk_id']
                                ? Produk::find($state['produk_id'])?->nama_produk.' (Selisih: '.($state['selisih'] ?? 0).')'
                                : null)
                            ->addActionLabel('Tambah Item')
                            ->required()
                            ->minItems(1),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
