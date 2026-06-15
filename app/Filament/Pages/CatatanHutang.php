<?php

namespace App\Filament\Pages;

use App\Models\Kontak;
use App\Models\Pembelian;
use App\Models\Pembayaran;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class CatatanHutang extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Kontak';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Catatan Hutang';

    protected static ?string $title = 'Catatan Hutang Kontak';

    protected string $view = 'filament.pages.catatan-hutang';

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, ['admin', 'spectator', 'super_admin']);
    }

    public function getCatatanHutang(): array
    {
        $user = Auth::user();

        // Ambil semua kontak yang memiliki pembelian Approved yang belum lunas
        $query = Kontak::whereHas('pembelians', function ($q) {
            $q->whereIn('status', ['Approved']);
        });

        // Scoping by role
        if ($user->role === 'admin') {
            $gudangIds = $user->gudangs->pluck('id')->toArray();
            if ($user->current_gudang_id) $gudangIds[] = $user->current_gudang_id;
            if ($user->gudang_id) $gudangIds[] = $user->gudang_id;
            $gudangIds = array_unique($gudangIds);
            $query->whereIn('gudang_id', $gudangIds);
        } elseif ($user->role === 'user') {
            $query->where('created_by', $user->id);
        }

        $kontaks = $query->orderBy('nama')->get();
        $result = [];

        foreach ($kontaks as $kontak) {
            $pembelians = $kontak->pembelians()
                ->whereIn('status', ['Approved'])
                ->get();

            $totalHutang = 0;
            $totalSisa = 0;
            $detailItems = [];

            foreach ($pembelians as $p) {
                $totalBayar = (float) Pembayaran::where('pembelian_id', $p->id)
                    ->where('status', 'Approved')->sum('jumlah_bayar');
                $sisa = max(0, (float) $p->grand_total - $totalBayar);
                $totalHutang += (float) $p->grand_total;
                $totalSisa += $sisa;
                $detailItems[] = [
                    'nomor' => $p->custom_number,
                    'gudang' => $p->gudang?->nama_gudang ?? '—',
                    'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y') ?? '—',
                    'grand_total' => $p->grand_total,
                    'sudah_bayar' => $totalBayar,
                    'sisa' => $sisa,
                    'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast(),
                ];
            }

            $result[] = [
                'kontak' => $kontak,
                'total_hutang' => $totalHutang,
                'total_sisa' => $totalSisa,
                'jumlah_transaksi' => count($pembelians),
                'items' => $detailItems,
            ];
        }

        // Sort by sisa hutang terbesar
        usort($result, fn($a, $b) => $b['total_sisa'] <=> $a['total_sisa']);

        return $result;
    }
}
