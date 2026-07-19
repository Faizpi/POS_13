<?php

namespace App\Filament\Pages;

use App\Exports\TransactionsExport;
use App\Filament\Widgets\AktivitasTerbaru;
use App\Filament\Widgets\ChartKomposisiStatus;
use App\Filament\Widgets\ChartPenjualanSales;
use App\Filament\Widgets\ChartTransaksiGudang;
use App\Filament\Widgets\ChartTrenPenjualan;
use App\Filament\Widgets\RingkasanDashboard;
use App\Models\Gudang;
use App\Models\User;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Get;
use Maatwebsite\Excel\Facades\Excel;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        $user = auth()->user();

        $widgets = [
            RingkasanDashboard::class,
        ];

        if ($user?->isAdmin()) {
            $widgets[] = ChartTrenPenjualan::class;
        }

        if ($user?->isAdmin()) {
            $widgets[] = ChartKomposisiStatus::class;
        }

        if ($user?->isAdmin() || $user?->isSpectator()) {
            $widgets[] = ChartTransaksiGudang::class;
            $widgets[] = ChartPenjualanSales::class;
            $widgets[] = AktivitasTerbaru::class;
        }

        return $widgets;
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    protected static function getExportFormatOptions(): array
    {
        $user = auth()->user();
        $options = [];

        if ($user?->canExportExcel()) {
            $options['excel'] = 'Excel';
        }

        if ($user?->canExportPdf()) {
            $options['pdf'] = 'PDF';
        }

        return $options;
    }

    protected static function getDefaultExportFormat(): ?string
    {
        $options = static::getExportFormatOptions();

        return array_key_first($options);
    }

    protected static function allowedTransactionTypes(): array
    {
        return ['semua', 'penjualan', 'pembelian', 'biaya', 'kunjungan', 'pembayaran'];
    }

    protected static function allowedStatuses(): array
    {
        return ['all', 'Pending', 'Approved', 'Lunas', 'Rejected', 'Canceled'];
    }

    protected static function allowedBiayaJenis(): array
    {
        return ['', 'masuk', 'keluar'];
    }

    protected static function allowedTujuanKunjungan(): array
    {
        return ['', 'Pemeriksaan Stock', 'Penagihan', 'Promo', 'Promo Gratis', 'Promo Sample', 'Penawaran'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Laporan')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->modalHeading('Export Laporan')
                ->modalWidth('lg')
                ->modalDescription('Pilih parameter laporan yang ingin di-export.')
                ->visible(fn (): bool => auth()->user()?->canExportReport() ?? false)
                ->form([
                    Select::make('tipe_transaksi')
                        ->label('Tipe Transaksi')
                        ->options([
                            'semua' => 'Semua Transaksi',
                            'penjualan' => 'Penjualan',
                            'pembelian' => 'Pembelian',
                            'biaya' => 'Biaya',
                            'kunjungan' => 'Kunjungan',
                            'pembayaran' => 'Pembayaran',
                        ])
                        ->default('semua')
                        ->live()
                        ->native(false)
                        ->required(),

                    Select::make('format')
                        ->label('Format')
                        ->options(fn (): array => static::getExportFormatOptions())
                        ->default(fn (): ?string => static::getDefaultExportFormat())
                        ->native(false)
                        ->required(),

                    DatePicker::make('start_date')
                        ->label('Tanggal Awal')
                        ->default(now()->startOfMonth())
                        ->required(),

                    DatePicker::make('end_date')
                        ->label('Tanggal Akhir')
                        ->default(now())
                        ->required(),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'all' => 'Semua Status',
                            'Pending' => 'Pending',
                            'Approved' => 'Approved',
                            'Lunas' => 'Lunas',
                            'Rejected' => 'Rejected',
                            'Canceled' => 'Canceled',
                        ])
                        ->default('all')
                        ->native(false),

                    Select::make('gudang_id')
                        ->label('Gudang')
                        ->options(function () {
                            $user = auth()->user();

                            if ($user?->role === 'super_admin') {
                                return Gudang::orderBy('nama_gudang')->pluck('nama_gudang', 'id');
                            }

                            if ($user?->role === 'admin') {
                                return $user->gudangs()->orderBy('nama_gudang')->pluck('nama_gudang', 'gudangs.id');
                            }

                            return [];
                        })
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Semua Gudang'),

                    Select::make('biaya_jenis')
                        ->label('Jenis Biaya')
                        ->options([
                            '' => 'Semua Jenis',
                            'masuk' => 'Masuk',
                            'keluar' => 'Keluar',
                        ])
                        ->default('')
                        ->visible(fn (Get $get): bool => $get('tipe_transaksi') === 'biaya')
                        ->native(false),

                    Select::make('tujuan_filter')
                        ->label('Tujuan Kunjungan')
                        ->options([
                            '' => 'Semua Tujuan',
                            'Pemeriksaan Stock' => 'Pemeriksaan Stock',
                            'Penagihan' => 'Penagihan',
                            'Promo' => 'Promo',
                            'Promo Gratis' => 'Promo Gratis',
                            'Promo Sample' => 'Promo Sample',
                            'Penawaran' => 'Penawaran',
                        ])
                        ->default('')
                        ->visible(fn (Get $get): bool => $get('tipe_transaksi') === 'kunjungan')
                        ->native(false),

                    Select::make('sales_id')
                        ->label('Sales')
                        ->options(function () {
                            $user = auth()->user();

                            if ($user?->role === 'super_admin') {
                                return User::where('role', 'user')->orderBy('name')->pluck('name', 'id');
                            }

                            if ($user?->role === 'admin') {
                                $gudangIds = $user->gudangs()->pluck('gudangs.id');

                                return User::where('role', 'user')
                                    ->whereIn('gudang_id', $gudangIds)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            }

                            return [];
                        })
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Semua Sales'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $format = $data['format'] ?? null;
                    $selectedType = $data['tipe_transaksi'] ?? 'semua';
                    $status = $data['status'] ?? 'all';
                    $biayaJenisInput = $data['biaya_jenis'] ?? '';
                    $tujuanFilterInput = $data['tujuan_filter'] ?? '';

                    if (! in_array($user?->role, ['super_admin', 'admin'], true)) {
                        Notification::make()
                            ->title('Akses ditolak')
                            ->body('Hanya admin dan super admin yang dapat export laporan.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! in_array($format, array_keys(static::getExportFormatOptions()), true)) {
                        Notification::make()
                            ->title('Format export tidak valid atau tidak diizinkan')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! in_array($selectedType, static::allowedTransactionTypes(), true)) {
                        Notification::make()
                            ->title('Tipe transaksi tidak valid')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! in_array($status, static::allowedStatuses(), true)) {
                        Notification::make()
                            ->title('Status laporan tidak valid')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! in_array($biayaJenisInput, static::allowedBiayaJenis(), true)) {
                        Notification::make()
                            ->title('Jenis biaya tidak valid')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! in_array($tujuanFilterInput, static::allowedTujuanKunjungan(), true)) {
                        Notification::make()
                            ->title('Tujuan kunjungan tidak valid')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! $user->canExportReport()) {
                        Notification::make()
                            ->title('Tidak memiliki izin export laporan')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if ($format === 'pdf' && ! $user->canExportPdf()) {
                        Notification::make()->title('Tidak memiliki izin export PDF')->danger()->send();

                        return null;
                    }

                    if ($format === 'excel' && ! $user->canExportExcel()) {
                        Notification::make()->title('Tidak memiliki izin export Excel')->danger()->send();

                        return null;
                    }

                    $gudangId = filled($data['gudang_id'] ?? null) ? (int) $data['gudang_id'] : null;

                    if ($gudangId && $user->role === 'admin' && ! $user->canAccessGudang($gudangId)) {
                        Notification::make()
                            ->title('Gudang tidak dapat diakses')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $type = $selectedType === 'semua' ? 'all' : $selectedType;
                    $dateFrom = Carbon::parse($data['start_date'])->toDateString();
                    $dateTo = Carbon::parse($data['end_date'])->toDateString();

                    if ($dateFrom > $dateTo) {
                        Notification::make()
                            ->title('Tanggal akhir harus sama atau setelah tanggal awal')
                            ->danger()
                            ->send();

                        return null;
                    }
                    $salesId = filled($data['sales_id'] ?? null) ? (int) $data['sales_id'] : null;
                    $biayaJenis = $type === 'biaya' && filled($biayaJenisInput) ? $biayaJenisInput : null;
                    $tujuanFilter = $type === 'kunjungan' && filled($tujuanFilterInput) ? $tujuanFilterInput : null;

                    $transactions = app(ReportExportService::class)->buildExportData(
                        $user,
                        $type,
                        $dateFrom,
                        $dateTo,
                        $status,
                        $gudangId,
                        $salesId,
                        $biayaJenis,
                        $tujuanFilter,
                    );

                    $fileBase = 'Laporan_'.ucfirst($type).'_'.str_replace('-', '', $dateFrom).'_sd_'.str_replace('-', '', $dateTo);

                    if ($format === 'pdf') {
                        return Pdf::loadView('reports.pdf', [
                            'transactions' => $transactions,
                            'exportType' => $type,
                            'dateFrom' => $dateFrom,
                            'dateTo' => $dateTo,
                            'generatedBy' => $user->name,
                            'generatedAt' => now()->format('d/m/Y H:i:s'),
                        ])->setPaper('a4', 'landscape')->download($fileBase.'.pdf');
                    }

                    return Excel::download(new TransactionsExport($transactions, $type, $user->name), $fileBase.'.xlsx');
                })
                ->modalSubmitActionLabel('Export')
                ->modalCancelActionLabel('Batal'),
        ];
    }
}
