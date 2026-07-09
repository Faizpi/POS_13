<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PiutangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $from = $request->filled('from') ? $request->from : now()->startOfYear()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        // --- Chart data ---
        $chartQuery = Penjualan::select(
            DB::raw("DATE_FORMAT(tgl_jatuh_tempo, '%Y-%m') as bulan"),
            DB::raw('SUM(grand_total) as total'),
            DB::raw('COUNT(*) as jumlah')
        )
            ->whereNotNull('tgl_jatuh_tempo')
            ->whereIn('status', ['Approved', 'Lunas']);

        if ($user->role === 'user') {
            $chartQuery->where('user_id', $user->id);
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            if ($user->current_gudang_id) {
                $chartQuery->where('gudang_id', $user->current_gudang_id);
            }
        }

        if ($gudangId) {
            $chartQuery->where('gudang_id', $gudangId);
        }
        $chartQuery->where('tgl_jatuh_tempo', '>=', $from);
        $chartQuery->where('tgl_jatuh_tempo', '<=', $to);

        $chartRows = $chartQuery->groupBy('bulan')->orderBy('bulan')->get();

        $chart = [
            'labels' => $chartRows->pluck('bulan')->map(fn ($b) => Carbon::parse($b.'-01')->format('M Y'))->toArray(),
            'totals' => $chartRows->pluck('total')->map(fn ($v) => (float) $v)->toArray(),
            'counts' => $chartRows->pluck('jumlah')->toArray(),
        ];

        // --- List toko (only for spectator + super_admin) ---
        $listToko = collect();

        if (in_array($user->role, ['spectator', 'super_admin'])) {
            $listQuery = Penjualan::with(['gudang'])
                ->whereIn('status', ['Approved', 'Lunas'])
                ->whereNotNull('tgl_jatuh_tempo');

            if ($gudangId) {
                $listQuery->where('gudang_id', $gudangId);
            }
            $listQuery->where('tgl_jatuh_tempo', '>=', $from);
            $listQuery->where('tgl_jatuh_tempo', '<=', $to);

            $listToko = $listQuery->orderBy('tgl_jatuh_tempo')->get()->map(function ($p) {
                $totalBayar = $p->pembayarans()->where('status', 'Approved')->sum('jumlah_bayar');
                $sisa = max(0, $p->grand_total - $totalBayar);

                return [
                    'nomor' => $p->custom_number,
                    'pelanggan' => $p->pelanggan,
                    'gudang' => $p->gudang?->nama_gudang,
                    'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y'),
                    'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast() && $p->status === 'Approved',
                    'grand_total' => $p->grand_total,
                    'sudah_bayar' => $totalBayar,
                    'sisa' => $sisa,
                    'status' => $p->status,
                ];
            });
        }

        return response()->json([
            'chart' => $chart,
            'list_toko' => $listToko,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'gudang_id' => $gudangId,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $user = auth()->user();
        $from = $request->filled('from') ? $request->from : now()->startOfYear()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        $query = Penjualan::with(['gudang'])
            ->whereIn('status', ['Approved', 'Lunas'])
            ->whereNotNull('tgl_jatuh_tempo')
            ->where('tgl_jatuh_tempo', '>=', $from)
            ->where('tgl_jatuh_tempo', '<=', $to);

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) {
                $query->where('gudang_id', $cg->id);
            } else {
                return response()->json(['message' => 'Tidak memiliki gudang aktif.'], 403);
            }
        }

        $list = $query->orderBy('tgl_jatuh_tempo')->get()->map(function ($p) {
            $totalBayar = $p->pembayarans()->where('status', 'Approved')->sum('jumlah_bayar');
            $sisa = max(0, $p->grand_total - $totalBayar);

            return [
                'nomor' => $p->custom_number,
                'pelanggan' => $p->pelanggan,
                'gudang' => $p->gudang?->nama_gudang,
                'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y'),
                'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast() && $p->status === 'Approved',
                'grand_total' => $p->grand_total,
                'sudah_bayar' => $totalBayar,
                'sisa' => $sisa,
                'status' => $p->status,
            ];
        });

        return PDF::loadView('reports.piutang-pdf', [
            'list' => $list,
            'from' => $from,
            'to' => $to,
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape')
            ->download('Piutang_'.Carbon::now()->format('Ymd').'.pdf');
    }
}
