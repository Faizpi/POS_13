<?php

namespace App\Filament\Resources\Kontaks\Pages;

use App\Filament\Resources\Kontaks\KontakResource as Resource;
use App\Models\Penjualan;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewKontak extends ViewRecord
{
    protected static string $resource = Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => Auth::user() && ! Auth::user()->isSpectator()),
            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn ($record) => route('kontak.download', $record->id))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('kontak.print', $record->id))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kontak')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('kode_kontak')
                            ->label('Kode Kontak')
                            ->weight('bold')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('nama')
                            ->label('Nama')
                            ->weight('bold'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('no_telp')
                            ->label('No. Telepon')
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state) => receipt_format_phone($state)),
                        TextEntry::make('pin')
                            ->label('PIN')
                            ->formatStateUsing(fn ($state) => $state ? '******' : 'Belum diatur')
                            ->placeholder('Belum diatur'),
                        TextEntry::make('alamat')
                            ->label('Alamat')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('diskon_persen')
                            ->label('Diskon')
                            ->suffix('%')
                            ->badge()
                            ->color('success'),
                        TextEntry::make('gudang.nama_gudang')
                            ->label('Gudang')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')
                            ->label('Diupdate')
                            ->dateTime('d M Y, H:i'),
                    ]),

                Section::make('Barcode & QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->schema([
                        View::make('filament.infolist.kontak-barcode'),
                    ]),

                Section::make('Catatan Hutang')
                    ->icon('heroicon-o-banknotes')
                    ->collapsible()
                    ->visible(fn ($record) => in_array(auth()->user()?->role, ['user', 'admin', 'super_admin']))
                    ->schema([
                        View::make('filament.infolist.catatan-hutang'),
                    ]),

                Section::make('Riwayat Penjualan')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        TextEntry::make('nama')
                            ->label('')
                            ->html()
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state, $record) {
                                $penjualans = Penjualan::where('pelanggan', $record->nama)
                                    ->with(['gudang', 'user'])
                                    ->orderBy('created_at', 'desc')
                                    ->limit(10)
                                    ->get();

                                if ($penjualans->isEmpty()) {
                                    return '<div class="text-center py-6 text-sm text-gray-400 dark:text-gray-500">Belum ada riwayat penjualan.</div>';
                                }

                                $rows = '';
                                foreach ($penjualans as $trx) {
                                    $statusClass = match ($trx->status) {
                                        'Approved' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                        'Lunas' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                        'Pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                        'Canceled' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
                                        default => 'bg-gray-100 text-gray-500',
                                    };
                                    $tgl = e($trx->tgl_transaksi?->format('d/m/Y') ?? '-');
                                    $nomor = e($trx->nomor ?? '-');
                                    $gudang = e($trx->gudang->nama_gudang ?? '-');
                                    $sales = e($trx->user->name ?? '-');
                                    $total = format_rupiah($trx->grand_total ?? 0);
                                    $status = e($trx->status);
                                    $url = url('/app/penjualans/'.$trx->id);

                                    $rows .= <<<ROW
                                    <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                        <td class="px-3 py-2.5 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">$tgl</td>
                                        <td class="px-3 py-2.5 text-sm font-semibold"><a href="$url" class="text-primary-600 hover:underline">$nomor</a></td>
                                        <td class="px-3 py-2.5 text-sm text-gray-600 dark:text-gray-400">$gudang</td>
                                        <td class="px-3 py-2.5 text-sm text-gray-600 dark:text-gray-400">$sales</td>
                                        <td class="px-3 py-2.5 text-sm font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">$total</td>
                                        <td class="px-3 py-2.5 text-center"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold $statusClass">$status</span></td>
                                    </tr>
                                    ROW;
                                }

                                return <<<HTML
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-left">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No. Invoice</th>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gudang</th>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sales</th>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-right">Grand Total</th>
                                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                            $rows
                                        </tbody>
                                    </table>
                                </div>
                                HTML;
                            }),
                    ]),
            ]);
    }
}
