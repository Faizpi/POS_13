<?php

namespace App\Filament\Customer\Pages\Auth;

use App\Models\Kontak;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Auth\Pages\Login as BaseAuth;
use Illuminate\Validation\ValidationException;

class CustomerLogin extends BaseAuth
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->getNoTelpFormComponent(),
                $this->getPinFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getNoTelpFormComponent(): Component
    {
        return TextInput::make('no_telp')
            ->label('Nomor Telepon')
            ->placeholder('Misal: 08123456789')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPinFormComponent(): Component
    {
        return TextInput::make('pin')
            ->label('PIN (6 Digit)')
            ->password()
            ->required()
            ->minLength(6)
            ->maxLength(6)
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'no_telp' => $data['no_telp'],
            'password' => $data['pin'],
        ];
    }

    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/login.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();

        // Custom authentication logic based on CustomerPortalController
        $noTelp = $data['no_telp'];
        $pin = $data['pin'];

        $kontak = Kontak::where('pin', $pin)
            ->where(function ($query) use ($noTelp) {
                // simple sanitize
                $clean = preg_replace('/[\s\-\.\(\)]+/', '', $noTelp);
                $query->where('no_telp', $clean)
                      ->orWhere('no_telp', 'like', '%' . ltrim($clean, '0'));
            })->first();

        if (!$kontak) {
            $this->throwFailureValidationException();
        }

        \Filament\Facades\Filament::auth()->login($kontak, $data['remember'] ?? false);

        session()->regenerate();

        return app(\Filament\Auth\Http\Responses\Contracts\LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.no_telp' => 'Nomor telepon atau PIN salah.',
        ]);
    }
}
