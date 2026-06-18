<?php

namespace App\Http\Controllers\Api;

use App\Exports\StokExport;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\StokLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class GudangController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->role === 'super_admin') {
            $gudangs = Gudang::all();
        } elseif ($user->role === 'admin') {
            $gudangs = $user->gudangs;
        } elseif ($user->role === 'spectator') {
            $gudangs = $user->spectatorGudangs;
        } else {
            $gudangs = $user->gudang ? collect([$user->gudang]) : collect();
        }

        return response()->json($gudangs);
    }

    public function switchGudang(Request $request)
    {
        $user = auth()->user();

        $request->validate(['gudang_id' => 'required|exists:gudangs,id']);

        if (! $user->canAccessGudang($request->gudang_id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $user->update(['current_gudang_id' => $request->gudang_id]);

        return response()->json([
            'message' => 'Gudang berhasil diganti.',
            'current_gudang' => Gudang::find($request->gudang_id),
        ]);
    }

    public function stok(Request $request)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $currentGudang = $user->getCurrentGudang();
        if (! $currentGudang && $user->role !== 'super_admin') {
            return response()->json(['data' => []]);
        }

        $query = GudangProduk::with(['produk:id,nama_produk,item_code,satuan', 'gudang:id,nama_gudang']);

        if ($user->role !== 'super_admin') {
            $query->where('gudang_id', $currentGudang->id);
        } elseif ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }

        return response()->json($query->get());
    }

    public function stokLog(Request $request)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = StokLog::with(['produk:id,nama_produk', 'gudang:id,nama_gudang', 'user:id,name']);

        $currentGudang = $user->getCurrentGudang();
        if ($user->role !== 'super_admin' && $currentGudang) {
            $query->where('gudang_id', $currentGudang->id);
        }

        return response()->json($query->latest()->paginate($request->per_page ?: 50));
    }

    public function exportStok(Request $request)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'spectator', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'gudang_id' => 'required|exists:gudangs,id',
        ]);

        $gudang = Gudang::findOrFail($request->gudang_id);

        if ($user->role !== 'super_admin' && ! $user->canAccessGudang($gudang->id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $stokData = GudangProduk::where('gudang_id', $gudang->id)
            ->with('produk')
            ->get();

        $fileName = 'Stok_'.str_replace(' ', '_', $gudang->nama_gudang).'_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new StokExport($gudang, $stokData, $user->name), $fileName);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membuat gudang.'], 403);
        }

        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat_gudang' => 'nullable|string|max:255',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => $request->nama_gudang,
            'alamat_gudang' => $request->alamat_gudang,
        ]);

        return response()->json(['message' => 'Gudang berhasil dibuat.', 'data' => $gudang], 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah gudang.'], 403);
        }

        $gudang = Gudang::findOrFail($id);

        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat_gudang' => 'nullable|string|max:255',
        ]);

        $gudang->update([
            'nama_gudang' => $request->nama_gudang,
            'alamat_gudang' => $request->alamat_gudang,
        ]);

        return response()->json(['message' => 'Gudang berhasil diperbarui.', 'data' => $gudang]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat menghapus gudang.'], 403);
        }

        $gudang = Gudang::findOrFail($id);

        if ($gudang->users()->exists()) {
            return response()->json(['message' => 'Gudang tidak dapat dihapus karena masih memiliki user terkait.'], 422);
        }

        if ($gudang->gudangProduks()->exists()) {
            return response()->json(['message' => 'Gudang tidak dapat dihapus karena masih memiliki produk terkait.'], 422);
        }

        $gudang->delete();

        return response()->json(['message' => 'Gudang berhasil dihapus.']);
    }
}
