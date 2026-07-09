<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GudangProduk;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StokLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = StockOpname::with(['user:id,name', 'gudang:id,nama_gudang'])
            ->withCount('items');

        if ($user->role === 'super_admin') {
            // all
        } elseif ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if ($currentGudang) {
                $query->where('gudang_id', $currentGudang->id);
            } else {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $opname = StockOpname::with(['user:id,name', 'gudang:id,nama_gudang', 'items.produk:id,nama_produk,item_code,satuan'])
            ->findOrFail($id);

        if ($user->role === 'user' && $opname->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (! $currentGudang || (int) $opname->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($opname);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Hanya admin dan super admin yang dapat membuat stock opname.'], 403);
        }

        $request->validate([
            'gudang_id' => 'required|exists:gudangs,id',
            'tgl_opname' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.qty_system' => 'required|numeric|min:0',
            'items.*.qty_aktual' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        // Gudang access check for admin
        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (! $currentGudang || (int) $request->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Gudang harus sesuai gudang aktif.'], 403);
            }
        }

        // Generate nomor SOP-YYYYMMDD-userId-XXX
        $countToday = StockOpname::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $nomor = StockOpname::generateNomor($user->id, $noUrut, Carbon::now());

        DB::beginTransaction();
        try {
            $opname = StockOpname::create([
                'user_id' => $user->id,
                'gudang_id' => $request->gudang_id,
                'nomor' => $nomor,
                'no_urut_harian' => $noUrut,
                'tgl_opname' => $request->tgl_opname,
                'status' => 'Draft',
                'memo' => $request->memo,
            ]);

            foreach ($request->items as $item) {
                $qtySystem = (float) $item['qty_system'];
                $qtyAktual = (float) $item['qty_aktual'];

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'produk_id' => $item['produk_id'],
                    'qty_system' => $qtySystem,
                    'qty_aktual' => $qtyAktual,
                    'selisih' => $qtyAktual - $qtySystem,
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Stock opname berhasil dibuat.',
                'data' => $opname->load('items'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat stock opname.'], 500);
        }
    }

    public function submit($id)
    {
        $user = auth()->user();
        $opname = StockOpname::findOrFail($id);

        if (! $opname->isDraft()) {
            return response()->json(['message' => 'Hanya stock opname dengan status Draft yang bisa disubmit.'], 422);
        }

        // Creator or admin/super_admin can submit
        if ($user->role === 'user' && $opname->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (! $currentGudang || (int) $opname->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Hanya bisa submit stock opname di gudang aktif.'], 403);
            }
        }

        $opname->update(['status' => 'Submitted']);

        return response()->json(['message' => 'Stock opname berhasil disubmit.', 'data' => $opname]);
    }

    public function apply($id)
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Hanya Super Admin yang dapat apply stock opname.'], 403);
        }

        $opname = StockOpname::with(['items.produk', 'gudang'])->findOrFail($id);

        if (! $opname->isSubmitted()) {
            return response()->json(['message' => 'Hanya stock opname dengan status Submitted yang bisa di-apply.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($opname->items as $item) {
                $gudangProduk = GudangProduk::firstOrNew([
                    'gudang_id' => $opname->gudang_id,
                    'produk_id' => $item->produk_id,
                ]);

                $stokSebelum = ($gudangProduk->stok_penjualan ?? 0)
                    + ($gudangProduk->stok_gratis ?? 0)
                    + ($gudangProduk->stok_sample ?? 0);

                $stokSesudah = (float) $item->qty_aktual;
                $selisih = $stokSesudah - $stokSebelum;

                // Update stok: set stok_penjualan = qty_aktual, zero-out others
                $gudangProduk->stok_penjualan = $stokSesudah;
                $gudangProduk->stok_gratis = 0;
                $gudangProduk->stok_sample = 0;
                $gudangProduk->stok = $stokSesudah;
                $gudangProduk->save();

                StokLog::create([
                    'gudang_produk_id' => $gudangProduk->id,
                    'produk_id' => $item->produk_id,
                    'gudang_id' => $opname->gudang_id,
                    'user_id' => $user->id,
                    'produk_nama' => $item->produk?->nama_produk,
                    'gudang_nama' => $opname->gudang?->nama_gudang,
                    'user_nama' => $user->name,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'selisih' => $selisih,
                    'keterangan' => 'Stock Opname: '.$opname->nomor,
                ]);
            }

            $opname->update([
                'status' => 'Applied',
                'approver_id' => $user->id,
            ]);

            DB::commit();

            return response()->json(['message' => 'Stock opname berhasil di-apply. Stok gudang telah diperbarui.', 'data' => $opname]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal apply stock opname.'], 500);
        }
    }
}
