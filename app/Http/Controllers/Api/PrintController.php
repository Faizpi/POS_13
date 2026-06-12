<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BluetoothPrintController;
use App\Http\Controllers\Controller;
use App\Models\Biaya;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\Penjualan;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function qrData(Request $request, $type, $id)
    {
        $model = $this->resolveModel($type, $id);
        if (! $model) {
            return response()->json(['message' => 'Tipe transaksi tidak valid.'], 404);
        }

        $user = auth()->user();
        if (! $this->canAccess($user, $model)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pathMap = ['penjualan' => 'penjualan', 'pembelian' => 'pembelian', 'biaya' => 'biaya', 'kunjungan' => 'kunjungan', 'pembayaran' => 'pembayaran', 'penerimaan-barang' => 'penerimaan-barang'];
        $publicPath = $pathMap[$type] ?? null;
        if (! $publicPath) {
            return response()->json(['message' => 'Tipe transaksi tidak didukung.'], 400);
        }

        return response()->json([
            'type' => $type, 'id' => $model->id, 'uuid' => $model->uuid,
            'receipt_url' => url("struk/{$publicPath}/{$model->uuid}"),
            'invoice_url' => url("invoice/{$publicPath}/{$model->uuid}"),
            'download_url' => url("invoice/{$publicPath}/{$model->uuid}/download"),
            'qr_payload' => url("invoice/{$publicPath}/{$model->uuid}"),
        ]);
    }

    public function bluetoothData(Request $request, $type, $id)
    {
        $model = $this->resolveModel($type, $id);
        if (! $model) {
            return response()->json(['message' => 'Tipe transaksi tidak valid.'], 404);
        }

        $user = auth()->user();
        if (! $this->canAccess($user, $model)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $printer = app(BluetoothPrintController::class);

        return match ($type) {
            'penjualan' => $printer->penjualanJson($model->id),
            'pembelian' => $printer->pembelianJson($model->id),
            'biaya' => $printer->biayaJson($model->id),
            'kunjungan' => $printer->kunjunganJson($model->id),
            default => response()->json([
                'message' => 'Bluetooth print belum tersedia untuk tipe ini.',
                'supported_types' => ['penjualan', 'pembelian', 'biaya', 'kunjungan'],
            ], 400),
        };
    }

    private function resolveModel($type, $id)
    {
        return match ($type) {
            'penjualan' => Penjualan::find($id),
            'pembelian' => Pembelian::find($id),
            'biaya' => Biaya::find($id),
            'kunjungan' => Kunjungan::find($id),
            'pembayaran' => Pembayaran::find($id),
            'penerimaan-barang' => PenerimaanBarang::find($id),
            default => null,
        };
    }

    private function canAccess($user, $model): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }
        if (isset($model->user_id) && (int) $model->user_id === (int) $user->id) {
            return true;
        }
        if (isset($model->approver_id) && $model->approver_id && (int) $model->approver_id === (int) $user->id) {
            return true;
        }
        if (isset($model->gudang_id) && $model->gudang_id && in_array($user->role, ['admin', 'spectator'])) {
            return $user->canAccessGudang($model->gudang_id);
        }

        return false;
    }
}
