<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\StockOpname;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateStockOpname extends CreateRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $countToday = StockOpname::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();

        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $data['user_id'] = $user->id;
        $data['uuid'] = (string) Str::uuid();
        $data['status'] = 'Draft'; // initial status
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = StockOpname::generateNomor($user->id, $noUrut, $now);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return StockOpnameResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
