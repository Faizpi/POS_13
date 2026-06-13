<?php

namespace App\Filament\Resources\Pembayarans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditPembayaran extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PembayaranResource::class;

    /**
     * Override form agar halaman edit HANYA menampilkan field lampiran.
     * Field lain (nomor, invoice, jumlah bayar, dll) tidak bisa diubah di sini.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bukti Pembayaran')
                ->icon('heroicon-o-paper-clip')
                ->description('Anda hanya dapat menambah, mengganti, atau menghapus lampiran pada pembayaran ini. Data pembayaran lainnya tidak dapat diubah.')
                ->schema([
                    FileUpload::make('lampiran_paths')
                        ->label('Lampiran / Bukti Bayar')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->disk('public')
                        ->directory('lampiran_pembayaran')
                        ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, $record): string {
                            $nomor = $record?->nomor ?? ('PAY-' . now()->format('Ymd') . '-' . auth()->id());
                            return "{$nomor}-" . time() . ".{$file->extension()}";
                        })
                        ->image()
                        ->imageEditor()
                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                        ->maxSize(5120)
                        ->columnSpanFull()
                        ->helperText('Bisa tambah beberapa file sekaligus. Format: gambar (JPG, PNG, WEBP) atau PDF. Maks 5 MB per file.'),
                ]),
        ]);
    }

    /**
     * Saat save, HANYA update lampiran_paths. Field lain tidak disentuh.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hanya perbolehkan lampiran_paths yang diperbarui
        return ['lampiran_paths' => $data['lampiran_paths'] ?? []];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn() => auth()->user()?->isSuperAdmin()),
        ];
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Lampiran pembayaran berhasil diperbarui.';
    }

    protected function getRedirectUrl(): string
    {
        return PembayaranResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
