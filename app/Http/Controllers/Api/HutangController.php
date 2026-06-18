<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HutangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from') ? $request->from : now()->startOfYear()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        // --- Chart data ---
        $chartQuery = Pembelian::select(
            DB::raw("DATE_FORMAT(tgl_jatuh_tempo, '%Y-%m') as bulan"),
            DB::raw('SUM(grand_total) as total'),
            DB::raw('COUNT(*) as jumlah')
        )
            ->whereNotNull('tgl_jatuh_tempo')
            ->whereIn('status', ['Approved', 'Lunas']);

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

        // --- List tempo ---
        $listQuery = Pembelian::with(['gudang', 'kontak'])
            ->whereIn('status', ['Approved', 'Lunas'])
            ->whereNotNull('tgl_jatuh_tempo');

        if ($gudangId) {
            $listQuery->where('gudang_id', $gudangId);
        }
        $listQuery->where('tgl_jatuh_tempo', '>=', $from);
        $listQuery->where('tgl_jatuh_tempo', '<=', $to);

        $listTempo = $listQuery->orderBy('tgl_jatuh_tempo')->get()->map(function ($p) {
            $totalBayar = $p->pembayarans()->where('status', 'Approved')->sum('jumlah_bayar');
            $sisa = max(0, $p->grand_total - $totalBayar);

            return [
                'nomor' => $p->custom_number,
                'supplier' => $p->kontak?->nama ?? '—',
                'gudang' => $p->gudang?->nama_gudang,
                'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y'),
                'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast() && $p->status === 'Approved',
                'grand_total' => $p->grand_total,
                'sudah_bayar' => $totalBayar,
                'sisa' => $sisa,
                'status' => $p->status,
            ];
        });

        return response()->json([
            'chart' => $chart,
            'list_tempo' => $listTempo,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'gudang_id' => $gudangId,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from') ? $request->from : now()->startOfYear()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        $query = Pembelian::with(['gudang', 'kontak'])
            ->whereIn('status', ['Approved', 'Lunas'])
            ->whereNotNull('tgl_jatuh_tempo')
            ->where('tgl_jatuh_tempo', '>=', $from)
            ->where('tgl_jatuh_tempo', '<=', $to);

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        $list = $query->orderBy('tgl_jatuh_tempo')->get()->map(function ($p) {
            $totalBayar = $p->pembayarans()->where('status', 'Approved')->sum('jumlah_bayar');
            $sisa = max(0, $p->grand_total - $totalBayar);

            return [
                'nomor' => $p->custom_number,
                'supplier' => $p->kontak?->nama ?? '—',
                'gudang' => $p->gudang?->nama_gudang,
                'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y'),
                'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast() && $p->status === 'Approved',
                'grand_total' => $p->grand_total,
                'sudah_bayar' => $totalBayar,
                'sisa' => $sisa,
                'status' => $p->status,
            ];
        });

        return PDF::loadView('reports.hutang-pdf', [
            'list' => $list,
            'from' => $from,
            'to' => $to,
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape')
            ->download('Hutang_'.Carbon::now()->format('Ymd').'.pdf');
    }
}
