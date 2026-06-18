<?php

namespace App\Http\Controllers\Api;

use App\Exports\NeracaExport;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Services\NeracaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class NeracaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['super_admin', 'spectator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from') ? $request->from : null;
        $to = $request->filled('to') ? $request->to : null;
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        $service = new NeracaService();
        $data = $service->getRingkasan($from, $to, $gudangId);

        return response()->json($data);
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['super_admin', 'spectator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from') ? $request->from : null;
        $to = $request->filled('to') ? $request->to : null;
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;
        $gudangName = 'Semua Gudang';

        if ($gudangId) {
            $gudang = Gudang::find($gudangId);
            $gudangName = $gudang?->nama_gudang ?? 'Semua Gudang';
        }

        $service = new NeracaService();
        $data = $service->getRingkasan($from, $to, $gudangId);

        $fileName = 'Neraca_'.($from ?? 'semua').'_sd_'.($to ?? 'semua').'.pdf';

        return Pdf::loadView('reports.neraca-pdf', [
            'data' => $data,
            'from' => $from,
            'to' => $to,
            'gudang' => $gudangName,
            'generatedBy' => $user->name,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ])->setPaper('a4', 'portrait')->download($fileName);
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['super_admin', 'spectator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from') ? $request->from : null;
        $to = $request->filled('to') ? $request->to : null;
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;
        $gudangName = 'Semua Gudang';

        if ($gudangId) {
            $gudang = Gudang::find($gudangId);
            $gudangName = $gudang?->nama_gudang ?? 'Semua Gudang';
        }

        $service = new NeracaService();
        $data = $service->getRingkasan($from, $to, $gudangId);

        $fileName = 'Neraca_'.($from ?? 'semua').'_sd_'.($to ?? 'semua').'.xlsx';

        return Excel::download(new NeracaExport($data, $from, $to, $gudangName), $fileName);
    }
}
