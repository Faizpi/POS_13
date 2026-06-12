<?php

namespace App\Filament\Pages;

use App\Models\TutupBuku as TutupBukuModel;
use App\Services\BackupService;
use App\Services\ExportService;
use App\Services\TutupBukuService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TutupBuku extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';
    protected static ?string $title = 'Tutup Buku & Backup';
    protected static ?string $slug = 'tutup-buku';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.tutup-buku';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TutupBukuModel::query()->latest('tahun'))
            ->columns([
                Tables\Columns\TextColumn::make('tahun')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        'pending' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('Ditutup Oleh'),
                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Tgl. Tutup')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('metadata_summary')
                    ->label('Ringkasan')
                    ->state(function (TutupBukuModel $record): string {
                        $meta = $record->metadata;
                        if (!$meta || !isset($meta['summary'])) return '-';

                        $parts = [];
                        foreach ($meta['summary'] as $key => $val) {
                            $label = match ($key) {
                                'penjualan' => 'Penjualan',
                                'pembelian' => 'Pembelian',
                                'biaya' => 'Biaya',
                                'kunjungan' => 'Kunjungan',
                                'pembayaran' => 'Pembayaran',
                                'penerimaan_barang' => 'Penerimaan',
                                default => $key,
                            };
                            $parts[] = "{$label}: {$val['archived']}";
                        }
                        return implode(', ', $parts);
                    }),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make('view_metadata')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detail Tutup Buku')
                    ->modalContent(function (TutupBukuModel $record): \Illuminate\Contracts\View\View {
                        $meta = $record->metadata ?? [];
                        return view('filament.pages.tutup-buku-detail', compact('meta', 'record'));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('tahun', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup_db')
                ->label('Backup Database')
                ->icon('heroicon-o-circle-stack')
                ->color('success')
                ->action(function () {
                    $backupService = app(BackupService::class);
                    $filename = $backupService->getBackupFilename();

                    return response()->streamDownload(function () use ($backupService) {
                        $generator = $backupService->generateSqlDump();
                        foreach ($generator as $chunk) {
                            echo $chunk;
                            flush();
                        }
                    }, $filename, [
                        'Content-Type' => 'application/sql',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                }),

            Action::make('backup_data')
                ->label('Export Data & Lampiran')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->form([
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options(fn () => $this->getYearOptions())
                        ->default(now()->year)
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $tahun = (int) $data['tahun'];
                    $exportService = app(ExportService::class);

                    try {
                        $zipPath = $exportService->exportYearlyData($tahun);
                        $zipFilename = basename($zipPath);

                        return response()->download($zipPath, $zipFilename, [
                            'Content-Type' => 'application/zip',
                        ])->deleteFileAfterSend(true);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Export Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return null;
                    }
                }),

            Action::make('tutup_buku')
                ->label('Tutup Buku Tahunan')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Tutup Buku Tahunan')
                ->modalDescription('Proses ini akan memindahkan semua data transaksi tahun yang dipilih ke tabel arsip. Data tidak dapat dikembalikan secara langsung. Pastikan sudah melakukan backup data terlebih dahulu!')
                ->form([
                    Select::make('tahun')
                        ->label('Tahun yang akan ditutup')
                        ->options(fn () => $this->getOpenYearOptions())
                        ->default(now()->subYear()->year)
                        ->native(false)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Catatan (opsional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $tahun = (int) $data['tahun'];
                    $notes = $data['notes'] ?? null;
                    $user = auth()->user();

                    try {
                        $service = app(TutupBukuService::class);
                        $service->execute($tahun, $user->id, $notes);

                        Notification::make()
                            ->title("Tutup Buku {$tahun} Berhasil!")
                            ->body("Semua data transaksi tahun {$tahun} telah dipindahkan ke tabel arsip.")
                            ->success()
                            ->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Validasi Gagal')
                            ->body($e->getMessage())
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Proses Gagal')
                            ->body('Terjadi kesalahan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function getYearOptions(): array
    {
        $currentYear = now()->year;
        $years = [];
        for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
            $years[$y] = (string) $y;
        }
        return $years;
    }

    private function getOpenYearOptions(): array
    {
        $years = $this->getYearOptions();
        $closedYears = TutupBukuModel::completed()->pluck('tahun')->toArray();

        foreach ($years as $year => $label) {
            if (in_array($year, $closedYears)) {
                $years[$year] = $label . ' (Sudah ditutup)';
            }
        }

        return $years;
    }
}
