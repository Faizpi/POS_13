<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Produk;
use App\Models\StokLog;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $currentGudang = $user->getCurrentGudang();

        if ($user->role === 'super_admin') {
            $gudangQuery = Gudang::query();
        } elseif ($user->role === 'admin') {
            $gudangQuery = $user->gudangs();
        } else {
            $gudangQuery = $user->spectatorGudangs();
        }

        if ($request->filled('gudang_id')) {
            $gudangId = (int) $request->gudang_id;
            if ($user->role !== 'super_admin' && !$user->canAccessGudang($gudangId)) {
                return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
            }
            $gudangQuery->where('gudangs.id', $gudangId);
        } elseif ($user->role !== 'super_admin' && $currentGudang) {
            $gudangQuery->where('gudangs.id', $currentGudang->id);
        }

        $gudangs = $gudangQuery->with(['gudangProduks' => function ($q) {
            $q->with('produk');
        }])->get();

        // Normalize stok total
        $gudangs->each(function ($gudang) {
            $gudang->gudangProduks->each(function ($stok) {
                $stok->stok = ($stok->stok_penjualan ?? 0) + ($stok->stok_gratis ?? 0) + ($stok->stok_sample ?? 0);
            });
        });

        return response()->json($gudangs);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang boleh mengubah stok manual.'], 403);
        }

        $request->validate([
            'gudang_id' => 'required|exists:gudangs,id',
            'produk_id' => 'required|exists:produks,id',
            'stok_penjualan' => 'required|integer|min:0',
            'stok_gratis' => 'required|integer|min:0',
            'stok_sample' => 'required|integer|min:0',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $produk = Produk::findOrFail($request->produk_id);
        $gudang = Gudang::findOrFail($request->gudang_id);

        $existing = GudangProduk::where('gudang_id', $request->gudang_id)
            ->where('produk_id', $request->produk_id)
            ->first();

        $stokSebelum = $existing ? $existing->stok : 0;
        $newTotal = $request->stok_penjualan + $request->stok_gratis + $request->stok_sample;
        $selisih = $newTotal - $stokSebelum;

        $gudangProduk = GudangProduk::updateOrCreate(
            ['gudang_id' => $request->gudang_id, 'produk_id' => $request->produk_id],
            [
                'stok' => $newTotal,
                'stok_penjualan' => $request->stok_penjualan,
                'stok_gratis' => $request->stok_gratis,
                'stok_sample' => $request->stok_sample,
            ]
        );

        if ($selisih != 0) {
            StokLog::create([
                'gudang_produk_id' => $gudangProduk->id,
                'produk_id' => $produk->id,
                'gudang_id' => $gudang->id,
                'user_id' => $user->id,
                'produk_nama' => $produk->nama_produk,
                'gudang_nama' => $gudang->nama_gudang,
                'user_nama' => $user->name,
                'stok_sebelum' => $stokSebelum,
                'stok_sesudah' => $newTotal,
                'selisih' => $selisih,
                'keterangan' => $request->keterangan ?? 'Perubahan stok manual via API',
            ]);
        }

        return response()->json(['message' => 'Stok berhasil diperbarui.', 'data' => $gudangProduk]);
    }

    public function log(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = StokLog::with(['produk:id,nama_produk', 'gudang:id,nama_gudang', 'user:id,name'])
            ->orderBy('created_at', 'desc');

        if ($user->role == 'admin') {
            $gudangIds = $user->gudangs()->pluck('gudangs.id');
            $query->whereIn('gudang_id', $gudangIds);
        }

        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }
        if ($request->filled('produk_id')) {
            $query->where('produk_id', $request->produk_id);
        }
        if ($request->filled('tanggal_dari')) {
            $query->whereDate('created_at', '>=', $request->tanggal_dari);
        }
        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('created_at', '<=', $request->tanggal_sampai);
        }

        return response()->json($query->paginate($request->per_page ?? 50));
    }
}
