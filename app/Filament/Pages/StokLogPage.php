<?php

namespace App\Filament\Pages;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StokLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StokLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Riwayat Stok';

    protected static ?string $title = 'Riwayat Perubahan Stok';

    protected string $view = 'filament.pages.stok-log';

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, ['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(function () use ($user): Builder {
                $query = StokLog::with(['produk:id,nama_produk', 'gudang:id,nama_gudang', 'user:id,name'])
                    ->orderByDesc('created_at');

                if ($user->role === 'admin') {
                    $gudangIds = $user->gudangs()->pluck('gudangs.id');
                    $query->whereIn('gudang_id', $gudangIds);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('produk_nama')
                    ->label('Produk')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->produk?->item_code),

                TextColumn::make('gudang_nama')
                    ->label('Gudang')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('stok_sebelum')
                    ->label('Sebelum')
                    ->alignCenter(),

                TextColumn::make('stok_sesudah')
                    ->label('Sesudah')
                    ->alignCenter()
                    ->weight('bold'),

                TextColumn::make('selisih')
                    ->label('Selisih')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->formatStateUsing(fn ($state): string => ($state > 0 ? '+' : '').number_format($state)),

                TextColumn::make('user_nama')
                    ->label('Diubah Oleh'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('produk_id')
                    ->label('Produk')
                    ->options(fn () => Produk::orderBy('nama_produk')->pluck('nama_produk', 'id'))
                    ->searchable()
                    ->preload(),

                Filter::make('tanggal_dari')
                    ->form([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['sampai'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->summaries(false, false)
            ->emptyStateHeading('Belum ada riwayat perubahan stok')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
