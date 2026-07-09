<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Biaya;
use App\Models\BiayaItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiayaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Biaya::with(['user:id,name', 'approver:id,name', 'gudang:id,nama_gudang']);

        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) {
                $query->where('gudang_id', $cg->id);
            } else {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }
        } elseif ($user->role !== 'super_admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('jenis')) {
            $query->where('jenis_biaya', $request->jenis);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $biaya = Biaya::with(['user:id,name', 'approver:id,name', 'items', 'gudang:id,nama_gudang'])->findOrFail($id);

        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $biaya->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role !== 'super_admin' && $biaya->user_id != $user->id && $biaya->approver_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($biaya);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'bayar_dari' => 'required|string',
            'tgl_transaksi' => 'required|date',
            'penerima' => 'nullable|string|max:255',
            'tax_percentage' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.kategori' => 'required|string|max:255',
            'items.*.jumlah' => 'required|numeric|min:0',
        ]);

        $initialStatus = 'Pending';
        $approverId = null;
        if ($user->role == 'super_admin') {
            $initialStatus = 'Approved';
            $approverId = $user->id;
        } else {
            $approverId = $this->findApprover($user);
        }

        $subTotal = collect($request->items)->sum('jumlah');
        $pajakPersen = $request->tax_percentage ?? 0;
        $grandTotal = $subTotal + ($subTotal * ($pajakPersen / 100));

        $countToday = Biaya::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
        $noUrut = $countToday + 1;
        $nomor = 'EXP-'.Carbon::now()->format('Ymd')."-{$user->id}-".str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        $gudangId = optional($user->getCurrentGudang())->id;

        DB::beginTransaction();
        try {
            $biaya = Biaya::create([
                'user_id' => $user->id, 'gudang_id' => $gudangId,
                'jenis_biaya' => $request->jenis_biaya ?? 'keluar',
                'nomor' => $nomor, 'no_urut_harian' => $noUrut,
                'bayar_dari' => $request->bayar_dari, 'penerima' => $request->penerima,
                'alamat_penagihan' => $request->alamat_penagihan,
                'tgl_transaksi' => $request->tgl_transaksi,
                'cara_pembayaran' => $request->cara_pembayaran,
                'tag' => $request->tag, 'koordinat' => $request->koordinat, 'memo' => $request->memo,
                'status' => $initialStatus, 'approver_id' => $approverId,
                'tax_percentage' => $pajakPersen, 'grand_total' => $grandTotal,
                'lampiran_paths' => [],
            ]);

            foreach ($request->items as $item) {
                BiayaItem::create([
                    'biaya_id' => $biaya->id,
                    'kategori' => $item['kategori'],
                    'deskripsi' => $item['deskripsi'] ?? null,
                    'jumlah' => $item['jumlah'],
                ]);
            }

            DB::commit();
            $msg = $initialStatus == 'Approved' ? 'Biaya berhasil disimpan dan langsung approved.' : 'Biaya berhasil dibuat.';

            return response()->json(['message' => $msg, 'data' => $biaya->load('items')], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat biaya.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $biaya = Biaya::findOrFail($id);
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah data biaya.'], 403);
        }

        return response()->json(['message' => 'Biaya berhasil diperbarui.', 'data' => $biaya->load('items')]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $biaya = Biaya::findOrFail($id);
        if ($biaya->status === 'Canceled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan, tidak bisa di-approve.'], 422);
        }
        if ($biaya->status === 'Approved' && $user->role === 'admin') {
            return response()->json(['message' => 'Transaksi sudah disetujui.'], 422);
        }

        if ($user->role === 'admin') {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $biaya->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Hanya bisa approve transaksi di gudang aktif.'], 403);
            }
        }

        $biaya->update(['status' => 'Approved', 'approver_id' => $user->id]);

        return response()->json(['message' => 'Biaya berhasil di-approve.', 'data' => $biaya]);
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $biaya = Biaya::findOrFail($id);
        if ($biaya->status === 'Canceled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }
        if ($biaya->status === 'Approved' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan transaksi yang sudah disetujui.'], 403);
        }

        $biaya->update(['status' => 'Canceled']);

        return response()->json(['message' => 'Biaya berhasil dibatalkan.']);
    }

    public function uncancel($id)
    {
        $biaya = Biaya::findOrFail($id);
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        }
        if ($biaya->status !== 'Canceled') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);
        }

        $biaya->update(['status' => 'Pending', 'approver_id' => $user->id]);

        return response()->json(['message' => 'Biaya berhasil di-uncancel. Status kembali ke Pending.', 'data' => $biaya]);
    }

    private function findApprover($user): ?int
    {
        $gudang = $user->getCurrentGudang();
        if ($user->role == 'user' && $gudang) {
            $admin = User::where('role', 'admin')->where(function ($q) use ($gudang) {
                $q->where('gudang_id', $gudang->id)->orWhereHas('gudangs', fn ($s) => $s->where('gudangs.id', $gudang->id));
            })->first();

            return $admin ? $admin->id : optional(User::where('role', 'super_admin')->first())->id;
        }

        return optional(User::where('role', 'super_admin')->first())->id;
    }
}
