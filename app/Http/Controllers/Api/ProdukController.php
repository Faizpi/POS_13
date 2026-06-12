<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\GudangProduk;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Produk::query();

        if ($user->role == 'user' && $user->gudang_id) {
            $query->whereHas('stokDiGudang', function ($q) use ($user) {
                $q->where('gudang_id', $user->gudang_id);
            });
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $currentGudang = $user->getCurrentGudang();
            if ($currentGudang) {
                $query->whereHas('stokDiGudang', function ($q) use ($currentGudang) {
                    $q->where('gudang_id', $currentGudang->id);
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_produk', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($request->per_page ?? 50));
    }

    public function show($id)
    {
        $produk = Produk::with('stokDiGudang.gudang:id,nama_gudang')->findOrFail($id);

        return response()->json($produk);
    }

    public function stokByGudang(Request $request, $gudangId)
    {
        $user = auth()->user();

        if ($user->role != 'super_admin' && !$user->canAccessGudang($gudangId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stok = GudangProduk::with('produk:id,nama_produk,item_code,satuan,harga,harga_grosir')
            ->where('gudang_id', $gudangId)
            ->get();

        return response()->json($stok);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'item_code' => 'nullable|string|max:255|unique:produks',
            'harga' => 'required|numeric|min:0',
            'harga_grosir' => 'nullable|numeric|min:0',
            'satuan' => 'required|in:Pcs,Lusin,Karton',
            'deskripsi' => 'nullable|string',
        ]);

        $produk = Produk::create($request->only('nama_produk', 'item_code', 'harga', 'harga_grosir', 'satuan', 'deskripsi'));

        return response()->json(['message' => 'Produk berhasil dibuat.', 'data' => $produk], 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $produk = Produk::findOrFail($id);

        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'item_code' => 'nullable|string|max:255|unique:produks,item_code,' . $produk->id,
            'harga' => 'required|numeric|min:0',
            'harga_grosir' => 'nullable|numeric|min:0',
            'satuan' => 'required|in:Pcs,Lusin,Karton',
            'deskripsi' => 'nullable|string',
        ]);

        $produk->update($request->only('nama_produk', 'item_code', 'harga', 'harga_grosir', 'satuan', 'deskripsi'));

        return response()->json(['message' => 'Produk berhasil diupdate.', 'data' => $produk]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $produk = Produk::findOrFail($id);
        $produk->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}
