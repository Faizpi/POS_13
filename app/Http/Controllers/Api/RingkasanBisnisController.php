<?php

namespace App\Http\Controllers\Api;

use App\Exports\RingkasanBisnisExport;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Services\RingkasanBisnisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RingkasanBisnisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authResult = $this->authorizeAndValidate($request);
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }

        [$user, $from, $to, $gudangId, $allowedWarehouseIds] = $authResult;

        $service = new RingkasanBisnisService;
        $data = $service->getRingkasan($from, $to, $gudangId, $allowedWarehouseIds);

        return response()->json($data);
    }

    public function exportPdf(Request $request)
    {
        $authResult = $this->authorizeAndValidate($request);
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }

        [$user, $from, $to, $gudangId, $allowedWarehouseIds] = $authResult;
        $gudangName = $this->getGudangName($gudangId);

        $service = new RingkasanBisnisService;
        $data = $service->getRingkasan($from, $to, $gudangId, $allowedWarehouseIds);

        $fileName = 'Ringkasan_Bisnis_'.($from ?? 'semua').'_sd_'.($to ?? 'semua').'.pdf';

        return Pdf::loadView('reports.ringkasan-bisnis-pdf', [
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
        $authResult = $this->authorizeAndValidate($request);
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }

        [$user, $from, $to, $gudangId, $allowedWarehouseIds] = $authResult;
        $gudangName = $this->getGudangName($gudangId);

        $service = new RingkasanBisnisService;
        $data = $service->getRingkasan($from, $to, $gudangId, $allowedWarehouseIds);

        $fileName = 'Ringkasan_Bisnis_'.($from ?? 'semua').'_sd_'.($to ?? 'semua').'.xlsx';

        return Excel::download(new RingkasanBisnisExport($data, $from, $to, $gudangName), $fileName);
    }

    /**
     * Authorize user and validate warehouse access.
     *
     * @return array|JsonResponse Returns [user, from, to, gudangId, allowedWarehouseIds] or error response
     */
    private function authorizeAndValidate(Request $request): array|JsonResponse
    {
        $user = auth()->user();

        if (! in_array($user->role, ['super_admin', 'spectator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from') ? $request->from : null;
        $to = $request->filled('to') ? $request->to : null;
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        // Validate gudang_id exists if provided
        if ($gudangId !== null && ! Gudang::where('id', $gudangId)->exists()) {
            return response()->json(['message' => 'Gudang tidak ditemukan'], 404);
        }

        // Determine allowed warehouse IDs based on role
        if ($user->role === 'super_admin') {
            $allowedWarehouseIds = null; // null means all warehouses
        } else {
            // spectator
            $allowedWarehouseIds = $user->spectatorGudangs()->pluck('gudangs.id')->toArray();

            // If specific gudang requested, validate access
            if ($gudangId !== null && ! in_array($gudangId, $allowedWarehouseIds)) {
                return response()->json(['message' => 'Tidak memiliki akses ke gudang ini'], 403);
            }
        }

        return [$user, $from, $to, $gudangId, $allowedWarehouseIds];
    }

    private function getGudangName(?int $gudangId): string
    {
        if ($gudangId === null) {
            return 'Semua Gudang';
        }

        return Gudang::where('id', $gudangId)->value('nama_gudang') ?? 'Semua Gudang';
    }
}
