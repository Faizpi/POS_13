<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class ProfilePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Profil Saya';

    protected static ?string $title = 'Profil Saya';

    protected string $view = 'filament.pages.profile';

    // Form fields
    public string $name = '';

    public string $alamat = '';

    public string $no_telp = '';

    public ?string $avatar = null;

    // Password change
    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->alamat = $user->alamat ?? '';
        $this->no_telp = $user->no_telp ?? '';
        $this->avatar = $user->avatar;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveProfile')
                ->label('Simpan Profil')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->form([
                    FileUpload::make('avatar')
                        ->label('Foto Profil')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->circleCropper()
                        ->directory('avatars')
                        ->disk('public')
                        ->maxSize(5120)
                        ->default(fn () => Auth::user()->avatar),

                    TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => Auth::user()->name),

                    TextInput::make('no_telp')
                        ->label('No. Telepon')
                        ->tel()
                        ->maxLength(20)
                        ->default(fn () => Auth::user()->no_telp),

                    Textarea::make('alamat')
                        ->label('Alamat')
                        ->rows(3)
                        ->default(fn () => Auth::user()->alamat),
                ])
                ->action(function (array $data): void {
                    $user = Auth::user();
                    $update = [
                        'name' => $data['name'],
                        'no_telp' => $data['no_telp'],
                        'alamat' => $data['alamat'],
                    ];
                    if (! empty($data['avatar'])) {
                        if ($user->avatar) {
                            @unlink(storage_path('app/public/'.$user->avatar));
                        }
                        $update['avatar'] = $data['avatar'];
                    }
                    $user->update($update);
                    $this->name = $data['name'];
                    $this->no_telp = $data['no_telp'] ?? '';
                    $this->alamat = $data['alamat'] ?? '';
                    $this->avatar = $update['avatar'] ?? $user->avatar;
                    Notification::make()->title('Profil berhasil diperbarui.')->success()->send();
                }),

            Action::make('changePassword')
                ->label('Ganti Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->form([
                    TextInput::make('current_password')
                        ->label('Password Saat Ini')
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('new_password')
                        ->label('Password Baru')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->same('new_password_confirmation'),
                    TextInput::make('new_password_confirmation')
                        ->label('Konfirmasi Password Baru')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = Auth::user();
                    if (! Hash::check($data['current_password'], $user->password)) {
                        Notification::make()->title('Password lama salah.')->danger()->send();

                        return;
                    }
                    $user->update(['password' => Hash::make($data['new_password'])]);
                    Notification::make()->title('Password berhasil diubah.')->success()->send();
                }),
        ];
    }
}
