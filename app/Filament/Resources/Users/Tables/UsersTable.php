<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Gudang;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=fff&background=random'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->email),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'spectator' => 'Spectator',
                        'user' => 'User',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'success',
                        'spectator' => 'info',
                        'user' => 'primary',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'super_admin' => 'heroicon-o-star',
                        'admin' => 'heroicon-o-briefcase',
                        'spectator' => 'heroicon-o-eye',
                        'user' => 'heroicon-o-user',
                        default => 'heroicon-o-user',
                    }),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('gudangs.nama_gudang')
                    ->label('Gudang Multi')
                    ->badge()
                    ->color('success')
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->toggleable(),

                ToggleColumn::make('receives_transaction_email')
                    ->label('Email')
                    ->alignCenter()
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger'),

                ToggleColumn::make('receives_transaction_whatsapp')
                    ->label('WA')
                    ->alignCenter()
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger'),

                ToggleColumn::make('can_export_pdf')
                    ->label('Exp PDF')
                    ->alignCenter()
                    ->toggleable()
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled(fn ($record) => $record?->role !== 'admin'),

                ToggleColumn::make('can_export_excel')
                    ->label('Exp Excel')
                    ->alignCenter()
                    ->toggleable()
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled(fn ($record) => $record?->role !== 'admin'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'spectator' => 'Spectator',
                        'admin' => 'Admin',
                        'user' => 'User',
                    ]),
                Filter::make('gudang')
                    ->form([
                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->options(Gudang::pluck('nama_gudang', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['gudang_id'])) return $query;

                        $gudangId = $data['gudang_id'];

                        return $query->where(function ($q) use ($gudangId) {
                            $q->where(function ($q1) use ($gudangId) {
                                    $q1->where('role', 'user')->where('gudang_id', $gudangId);
                                })
                                ->orWhere(function ($q2) use ($gudangId) {
                                    $q2->where('role', 'admin')
                                        ->whereIn('id', function ($sub) use ($gudangId) {
                                            $sub->select('user_id')
                                                ->from('admin_gudang')
                                                ->where('gudang_id', $gudangId);
                                        });
                                })
                                ->orWhere(function ($q3) use ($gudangId) {
                                    $q3->where('role', 'spectator')
                                        ->whereIn('id', function ($sub) use ($gudangId) {
                                            $sub->select('user_id')
                                                ->from('spectator_gudang')
                                                ->where('gudang_id', $gudangId);
                                        });
                                });
                        });
                    }),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->orderByRaw("CASE users.role WHEN 'super_admin' THEN 1 WHEN 'spectator' THEN 2 WHEN 'admin' THEN 3 WHEN 'user' THEN 4 ELSE 5 END")
                ->orderByRaw("(SELECT MIN(g.nama_gudang) FROM admin_gudang ag JOIN gudangs g ON g.id = ag.gudang_id WHERE ag.user_id = users.id)")
                ->orderByRaw("(SELECT MIN(g.nama_gudang) FROM spectator_gudang sg JOIN gudangs g ON g.id = sg.gudang_id WHERE sg.user_id = users.id)")
                ->orderByRaw("(SELECT g.nama_gudang FROM gudangs g WHERE g.id = users.gudang_id)")
                ->orderBy('users.name')
            )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->id() !== $record->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Tambahkan pengguna pertama untuk mulai menggunakan sistem.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
