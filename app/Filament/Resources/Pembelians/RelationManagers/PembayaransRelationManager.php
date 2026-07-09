<?php

namespace App\Filament\Resources\Pembelians\RelationManagers;

use App\Filament\Concerns\TransactionDeleteGuard;
use App\Models\Gudang;
use App\Models\Pembayaran;
use App\Services\PaymentSettlementService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PembayaransRelationManager extends RelationManager
{
    protected static string $relationship = 'pembayarans';

    protected static ?string $title = 'Pembayaran Hutang';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nomor')
                    ->label('No Transaksi')
                    ->default(function () {
                        $owner = $this->getOwnerRecord();
                        $countToday = Pembayaran::where('type', 'hutang')
                            ->whereDate('created_at', now())
                            ->count();

                        return 'BAYH-'.now()->format('Ymd').'-'.auth()->id().'-'.str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
                    })
                    ->disabled()
                    ->dehydrated()
                    ->maxLength(255),

                Select::make('gudang_id')
                    ->label('Gudang')
                    ->required()
                    ->options(fn () => Gudang::pluck('nama_gudang', 'id'))
                    ->default(fn () => $this->getOwnerRecord()->gudang_id)
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('invoice_pembelian')
                    ->label('Invoice Pembelian')
                    ->default(fn () => $this->getOwnerRecord()->nomor)
                    ->disabled()
                    ->dehydrated(),

                Select::make('metode_pembayaran')
                    ->label('Metode Pembayaran')
                    ->required()
                    ->options([
                        'Transfer Bank' => 'Transfer Bank',
                        'Tunai' => 'Tunai',
                        'Cek' => 'Cek',
                        'Giro' => 'Giro',
                    ])
                    ->native(false),

                DatePicker::make('tgl_pembayaran')
                    ->label('Tanggal Pembayaran')
                    ->required()
                    ->default(now()),

                TextInput::make('jumlah_bayar')
                    ->label('Jumlah Bayar')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(fn () => $this->getOwnerRecord()->grand_total),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),

                FileUpload::make('lampiran_paths')
                    ->label('Bukti Pembayaran')
                    ->multiple()
                    ->disk('public')
                    ->directory('lampiran_pembayaran_hutang')
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxSize(5120)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nomor')
            ->columns([
                TextColumn::make('nomor')
                    ->label('No Transaksi')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('tgl_pembayaran')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('metode_pembayaran')
                    ->label('Metode')
                    ->badge()
                    ->color('info'),

                TextColumn::make('jumlah_bayar')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'success',
                        'Canceled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Catat Pembayaran')
                    ->visible(fn () => in_array(auth()->user()?->role, ['user', 'admin', 'super_admin']))
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        app(PaymentSettlementService::class)->assertHutangPaymentCanBeCreated($owner, $data['jumlah_bayar']);

                        $data['type'] = 'hutang';
                        $data['user_id'] = auth()->id();
                        $data['gudang_id'] = $owner->gudang_id;
                        $data['pembelian_id'] = $owner->id;
                        $data['uuid'] = (string) Str::uuid();
                        $data['status'] = 'Pending';

                        // Generate nomor
                        $countToday = Pembayaran::where('type', 'hutang')
                            ->whereDate('created_at', now())
                            ->count();
                        $data['nomor'] = 'BAYH-'.now()->format('Ymd').'-'.auth()->id().'-'.str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                DeleteAction::make()
                    ->visible(fn ($record): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePembayaran($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Catat pembayaran hutang untuk transaksi ini.');
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Sales & Admin bisa add+view, Spectator view only, SuperAdmin all
        return in_array($user->role, ['user', 'admin', 'spectator', 'super_admin']);
    }
}
