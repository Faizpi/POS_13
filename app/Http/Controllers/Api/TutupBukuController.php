<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TutupBuku;
use App\Services\BackupService;
use App\Services\ExportService;
use App\Services\TutupBukuService;
use Illuminate\Http\Request;

class TutupBukuController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $records = TutupBuku::with(['closedBy:id,name'])
            ->latest('tahun')
            ->get();

        return response()->json($records);
    }

    public function execute(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'tahun' => 'required|integer',
        ]);

        $tahun = $request->integer('tahun');
        $notes = $request->input('notes');

        try {
            $service = app(TutupBukuService::class);
            $record = $service->execute($tahun, $user->id, $notes);

            return response()->json(['message' => "Tutup Buku {$tahun} berhasil.", 'data' => $record]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menjalankan tutup buku.'], 500);
        }
    }

    public function backupDb()
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $backupService = app(BackupService::class);
        $filename = $backupService->getBackupFilename();

        return response()->streamDownload(function () use ($backupService) {
            $generator = $backupService->generateSqlDump();
            foreach ($generator as $chunk) {
                echo $chunk;
                flush();
            }
        }, $filename, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportData(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'tahun' => 'required|integer',
        ]);

        $tahun = $request->integer('tahun');
        $gudangId = $request->filled('gudang_id') ? $request->integer('gudang_id') : null;

        try {
            $exportService = app(ExportService::class);
            $zipPath = $exportService->exportYearlyData($tahun, $gudangId);
            $zipFilename = basename($zipPath);

            return response()->download($zipPath, $zipFilename, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Export gagal: '.$e->getMessage()], 500);
        }
    }
}
