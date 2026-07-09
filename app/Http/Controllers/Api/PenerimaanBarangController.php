<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\User;
use App\Services\InventoryMutationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PenerimaanBarangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PenerimaanBarang::with(['user:id,name', 'approver:id,name', 'gudang:id,nama_gudang', 'pembelian:id,nomor']);

        if ($user->role == 'super_admin') {
            // all
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) {
                $query->where('gudang_id', $cg->id);
            } else {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $penerimaan = PenerimaanBarang::with(['user:id,name', 'approver:id,name', 'gudang:id,nama_gudang', 'pembelian:id,nomor', 'items.produk:id,nama_produk,item_code,satuan'])->findOrFail($id);

        if ($user->role == 'user' && $penerimaan->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $penerimaan->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Tidak memiliki akses ke gudang aktif untuk data ini.'], 403);
            }
        }

        return response()->json($penerimaan);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'gudang_id' => 'required|exists:gudangs,id',
            'pembelian_id' => 'required|exists:pembelians,id',
            'tgl_penerimaan' => 'required|date',
            'no_surat_jalan' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.qty_diterima' => 'required|integer|min:0',
            'items.*.qty_reject' => 'nullable|integer|min:0',
            'items.*.tipe_stok' => 'nullable|in:penjualan,gratis,sample',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
        ]);

        $gudangId = $request->gudang_id;
        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $gudangId !== (int) $cg->id) {
                return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && ! $user->canAccessGudang($gudangId)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $pembelian = Pembelian::findOrFail($request->pembelian_id);
        if ($pembelian->gudang_id != $gudangId) {
            return response()->json(['message' => 'Pembelian tidak valid untuk gudang yang dipilih.'], 422);
        }

        $approverId = null;
        $initialStatus = 'Pending';
        if ($user->role == 'super_admin') {
            $initialStatus = 'Approved';
            $approverId = $user->id;
        } else {
            $approverId = $this->findApprover($user, $gudangId);
        }

        $countToday = PenerimaanBarang::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
        $noUrut = $countToday + 1;
        $nomor = PenerimaanBarang::generateNomor($user->id, $noUrut, Carbon::now());

        DB::beginTransaction();
        try {
            $pembelian = $this->lockPembelianWithItems((int) $request->pembelian_id);
            $this->validateItemsDoNotExceedRemaining($pembelian, $request->items);

            $penerimaan = PenerimaanBarang::create([
                'user_id' => $user->id,
                'approver_id' => $approverId,
                'gudang_id' => $gudangId,
                'pembelian_id' => $request->pembelian_id,
                'no_urut_harian' => $noUrut,
                'nomor' => $nomor,
                'tgl_penerimaan' => $request->tgl_penerimaan,
                'no_surat_jalan' => $request->no_surat_jalan,
                'lampiran_paths' => [],
                'keterangan' => $request->keterangan,
                'status' => $initialStatus,
            ]);

            foreach ($request->items as $item) {
                $qtyDiterima = $item['qty_diterima'] ?? 0;
                $qtyReject = $item['qty_reject'] ?? 0;
                if ($qtyDiterima <= 0 && $qtyReject <= 0) {
                    continue;
                }

                $tipeStok = $item['tipe_stok'] ?? 'penjualan';

                PenerimaanBarangItem::create([
                    'penerimaan_barang_id' => $penerimaan->id,
                    'produk_id' => $item['produk_id'],
                    'qty_diterima' => $qtyDiterima,
                    'qty_reject' => $qtyReject,
                    'tipe_stok' => $tipeStok,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expired_date' => $item['expired_date'] ?? null,
                    'keterangan' => $item['keterangan'] ?? null,
                ]);

                if ($initialStatus === 'Approved' && $qtyDiterima > 0) {
                    $this->tambahStok($gudangId, $item['produk_id'], $qtyDiterima, $tipeStok, $penerimaan);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Penerimaan barang berhasil dibuat.', 'data' => $penerimaan->load('items')], 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (DomainException|InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat penerimaan barang.'], 500);
        }
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $penerimaan = PenerimaanBarang::with('items')->findOrFail($id);
        if ($penerimaan->status !== 'Pending') {
            return response()->json(['message' => 'Hanya transaksi Pending yang bisa di-approve.'], 422);
        }
        if ($user->role === 'admin') {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $penerimaan->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Hanya bisa approve transaksi di gudang aktif.'], 403);
            }
        }

        DB::beginTransaction();
        try {
            $penerimaan = PenerimaanBarang::with('items')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penerimaan->status !== 'Pending') {
                DB::rollBack();

                return response()->json(['message' => 'Hanya transaksi Pending yang bisa di-approve.'], 422);
            }

            $pembelian = $this->lockPembelianWithItems((int) $penerimaan->pembelian_id);
            $this->validateItemsDoNotExceedRemaining($pembelian, $penerimaan->items->all());

            $penerimaan->update(['status' => 'Approved', 'approver_id' => $user->id]);
            foreach ($penerimaan->items as $item) {
                if ($item->qty_diterima > 0) {
                    $this->tambahStok($penerimaan->gudang_id, $item->produk_id, $item->qty_diterima, $item->tipe_stok ?? 'penjualan', $penerimaan, 'Penerimaan Approve');
                }
            }
            DB::commit();

            return response()->json(['message' => 'Penerimaan barang berhasil di-approve dan stok ditambahkan.', 'data' => $penerimaan->refresh()]);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (DomainException|InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal approve penerimaan barang.'], 500);
        }
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $penerimaan = PenerimaanBarang::with('items')->findOrFail($id);
        if ($penerimaan->status === 'Canceled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }
        if ($penerimaan->status === 'Approved' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan transaksi yang sudah disetujui.'], 403);
        }

        DB::beginTransaction();
        try {
            $penerimaan = PenerimaanBarang::with('items')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penerimaan->status === 'Canceled') {
                DB::rollBack();

                return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
            }

            if ($penerimaan->status === 'Approved') {
                foreach ($penerimaan->items as $item) {
                    if ((int) $item->qty_diterima > 0) {
                        $this->kurangiStok($penerimaan->gudang_id, $item->produk_id, $item->qty_diterima, $item->tipe_stok ?? 'penjualan', $penerimaan, 'Penerimaan Cancel');
                    }
                }
            }
            $penerimaan->update(['status' => 'Canceled']);
            DB::commit();
        } catch (DomainException|InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membatalkan penerimaan barang.'], 500);
        }

        return response()->json(['message' => 'Penerimaan barang berhasil dibatalkan.']);
    }

    public function uncancel($id)
    {
        $penerimaan = PenerimaanBarang::findOrFail($id);
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        }
        if ($penerimaan->status !== 'Canceled') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);
        }

        $penerimaan->update(['status' => 'Pending']);

        return response()->json(['message' => 'Penerimaan barang berhasil di-uncancel. Status kembali ke Pending.', 'data' => $penerimaan]);
    }

    public function getPembelianByGudang($gudangId)
    {
        $user = auth()->user();
        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $gudangId !== (int) $cg->id) {
                return response()->json(['message' => 'Tidak memiliki akses ke gudang aktif ini.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && ! $user->canAccessGudang($gudangId)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $pembelians = Pembelian::where('gudang_id', $gudangId)
            ->whereIn('status', ['Approved', 'Pending'])
            ->with('items.produk')
            ->get()
            ->filter(function ($pembelian) {
                foreach ($pembelian->items as $item) {
                    $qtyDiterima = PenerimaanBarangItem::whereHas('penerimaanBarang', function ($q) use ($pembelian) {
                        $q->where('pembelian_id', $pembelian->id)->where('status', 'Approved');
                    })->where('produk_id', $item->produk_id)->sum('qty_diterima');
                    if (($item->kuantitas ?? 0) - $qtyDiterima > 0) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn ($p) => [
                'id' => $p->id,
                'nomor' => $p->nomor ?? 'PO-'.$p->id,
                'tgl_transaksi' => $p->tgl_transaksi?->format('Y-m-d'),
                'status' => $p->status,
                'total_items' => $p->items->count(),
            ])->values();

        return response()->json($pembelians);
    }

    public function getPembelianDetail($id)
    {
        $user = auth()->user();
        $pembelian = Pembelian::with('items.produk')->findOrFail($id);

        if ($user->role == 'user' && $pembelian->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $qtyDiterima = [];
        $penerimaanItems = PenerimaanBarangItem::whereHas('penerimaanBarang', function ($q) use ($id) {
            $q->where('pembelian_id', $id)->where('status', 'Approved');
        })->get();
        foreach ($penerimaanItems as $item) {
            $qtyDiterima[$item->produk_id] = ($qtyDiterima[$item->produk_id] ?? 0) + $item->qty_diterima;
        }

        $items = $pembelian->items->map(function ($item) use ($qtyDiterima) {
            $sudah = $qtyDiterima[$item->produk_id] ?? 0;
            $qty = $item->kuantitas ?? 0;

            return [
                'produk_id' => $item->produk_id,
                'nama_produk' => $item->produk?->nama_produk,
                'item_code' => $item->produk?->item_code,
                'qty_pesan' => $qty,
                'qty_diterima' => $sudah,
                'qty_sisa' => max(0, $qty - $sudah),
                'satuan' => $item->produk?->satuan ?? 'Pcs',
            ];
        });

        return response()->json([
            'id' => $pembelian->id,
            'nomor' => $pembelian->nomor,
            'tgl_transaksi' => $pembelian->tgl_transaksi?->format('Y-m-d'),
            'items' => $items,
        ]);
    }

    private function lockPembelianWithItems(int $pembelianId): Pembelian
    {
        $pembelian = Pembelian::query()
            ->whereKey($pembelianId)
            ->lockForUpdate()
            ->firstOrFail();

        $items = $pembelian->items()
            ->with('produk:id,nama_produk')
            ->lockForUpdate()
            ->get();

        $pembelian->setRelation('items', $items);

        return $pembelian;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $items
     */
    private function validateItemsDoNotExceedRemaining(Pembelian $pembelian, iterable $items): void
    {
        $orderedQuantities = [];
        $productNames = [];

        foreach ($pembelian->items as $purchaseItem) {
            $produkId = (int) $purchaseItem->produk_id;
            $orderedQuantities[$produkId] = ($orderedQuantities[$produkId] ?? 0) + (int) ($purchaseItem->kuantitas ?? $purchaseItem->jumlah ?? 0);
            $productNames[$produkId] = $purchaseItem->produk?->nama_produk ?? "ID {$produkId}";
        }

        $approvedQuantities = $this->approvedReceivedQuantities((int) $pembelian->id);
        $requestedQuantities = [];
        $firstIndexes = [];

        foreach ($items as $index => $item) {
            $qtyDiterima = (int) $this->itemValue($item, 'qty_diterima', 0);
            if ($qtyDiterima <= 0) {
                continue;
            }

            $produkId = (int) $this->itemValue($item, 'produk_id', 0);
            $requestedQuantities[$produkId] = ($requestedQuantities[$produkId] ?? 0) + $qtyDiterima;
            $firstIndexes[$produkId] ??= is_int($index) ? $index : 0;
        }

        foreach ($requestedQuantities as $produkId => $requestedQuantity) {
            $orderedQuantity = $orderedQuantities[$produkId] ?? 0;
            $approvedQuantity = $approvedQuantities[$produkId] ?? 0;
            $remainingQuantity = max(0, $orderedQuantity - $approvedQuantity);

            if ($requestedQuantity > $remainingQuantity) {
                $productName = $productNames[$produkId] ?? "ID {$produkId}";
                $index = $firstIndexes[$produkId] ?? 0;

                throw ValidationException::withMessages([
                    "items.{$index}.qty_diterima" => "Qty diterima melebihi sisa PO. Produk {$productName}: sisa {$remainingQuantity}, diminta {$requestedQuantity}.",
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function approvedReceivedQuantities(int $pembelianId): array
    {
        $approvedReceiptIds = PenerimaanBarang::query()
            ->where('pembelian_id', $pembelianId)
            ->where('status', 'Approved')
            ->lockForUpdate()
            ->pluck('id');

        if ($approvedReceiptIds->isEmpty()) {
            return [];
        }

        return PenerimaanBarangItem::query()
            ->select('produk_id', DB::raw('SUM(qty_diterima) as total_qty_diterima'))
            ->whereIn('penerimaan_barang_id', $approvedReceiptIds)
            ->groupBy('produk_id')
            ->lockForUpdate()
            ->pluck('total_qty_diterima', 'produk_id')
            ->map(fn ($quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * @param  array<string, mixed>|object  $item
     */
    private function itemValue(array|object $item, string $key, mixed $default = null): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }

        return $item->{$key} ?? $default;
    }

    private function tambahStok($gudangId, $produkId, $qty, $tipeStok = 'penjualan', ?PenerimaanBarang $penerimaan = null, string $transactionType = 'Penerimaan Approve'): void
    {
        app(InventoryMutationService::class)->increment(
            (int) $gudangId,
            (int) $produkId,
            (int) $qty,
            (string) $tipeStok,
            $penerimaan ? [
                'transaction_type' => $transactionType,
                'transaction_id' => $penerimaan->id,
                'transaction_nomor' => $penerimaan->nomor,
            ] : null,
        );
    }

    private function kurangiStok($gudangId, $produkId, $qty, $tipeStok = 'penjualan', ?PenerimaanBarang $penerimaan = null, string $transactionType = 'Penerimaan Cancel'): void
    {
        app(InventoryMutationService::class)->decrement(
            (int) $gudangId,
            (int) $produkId,
            (int) $qty,
            (string) $tipeStok,
            $penerimaan ? [
                'transaction_type' => $transactionType,
                'transaction_id' => $penerimaan->id,
                'transaction_nomor' => $penerimaan->nomor,
            ] : null,
        );
    }

    private function findApprover($user, $gudangId): ?int
    {
        if ($user->role == 'user') {
            $admin = User::where('role', 'admin')->where(function ($q) use ($gudangId) {
                $q->where('gudang_id', $gudangId)->orWhereHas('gudangs', fn ($s) => $s->where('gudangs.id', $gudangId));
            })->first();

            return $admin ? $admin->id : optional(User::where('role', 'super_admin')->first())->id;
        }
        if ($user->role == 'admin') {
            return optional(User::where('role', 'super_admin')->first())->id;
        }

        return $user->id;
    }
}
