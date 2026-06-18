<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kontak;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CatatanHutangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Defensive check: pastikan kolom kontak_id ada di tabel pembelians
        if (! Schema::hasColumn('pembelians', 'kontak_id')) {
            return response()->json([]);
        }

        // Ambil semua kontak yang memiliki pembelian Approved
        $query = Kontak::whereHas('pembelians', function ($q) {
            $q->whereIn('status', ['Approved']);
        });

        // Scoping by role
        if ($user->role === 'admin') {
            $gudangIds = $user->gudangs->pluck('id')->toArray();
            if ($user->current_gudang_id) {
                $gudangIds[] = $user->current_gudang_id;
            }
            if ($user->gudang_id) {
                $gudangIds[] = $user->gudang_id;
            }
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
                'kontak' => [
                    'id' => $kontak->id,
                    'nama' => $kontak->nama,
                    'no_telp' => $kontak->no_telp,
                ],
                'total_hutang' => $totalHutang,
                'total_sisa' => $totalSisa,
                'jumlah_transaksi' => count($pembelians),
                'items' => $detailItems,
            ];
        }

        // Sort by sisa hutang terbesar
        usort($result, fn ($a, $b) => $b['total_sisa'] <=> $a['total_sisa']);

        return response()->json($result);
    }
}
