<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\User;
use App\Services\Accounting\HutangPostingService;
use App\Services\PurchaseMoneyCalculator;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PembelianController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Pembelian::with(['user:id,name', 'gudang:id,nama_gudang', 'approver:id,name']);

        if ($user->role == 'super_admin') {
            // all
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
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
        if ($request->filled('search')) {
            $query->where('nomor', 'like', "%{$request->search}%");
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $pembelian = Pembelian::with(['user:id,name', 'gudang:id,nama_gudang', 'approver:id,name', 'items.produk:id,nama_produk,item_code,satuan'])->findOrFail($id);

        if ($user->role == 'user' && $pembelian->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $pembelian->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($pembelian);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'tgl_transaksi' => 'required|date',
            'syarat_pembayaran' => 'required|string',
            'urgensi' => 'required|string',
            'gudang_id' => 'required|exists:gudangs,id',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'diskon_akhir' => 'nullable|numeric|min:0',
            'biaya_pengiriman' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.kuantitas' => 'required|numeric|min:1',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.diskon' => 'nullable|numeric|min:0|max:100',
        ]);

        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $request->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && ! $user->canAccessGudang($request->gudang_id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $totals = $this->calculatePurchaseTotals(
            $request->items,
            $request->diskon_akhir ?? 0,
            $request->tax_percentage ?? 0,
            $request->biaya_pengiriman ?? 0,
        );

        $term = $request->syarat_pembayaran;
        $tglJatuhTempo = null;
        if ($term != 'Cash') {
            $tglJatuhTempo = Carbon::parse($request->tgl_transaksi);
            $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
            if (isset($days[$term])) {
                $tglJatuhTempo->addDays($days[$term]);
            }
        }

        $countToday = Pembelian::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
        $noUrut = $countToday + 1;
        $nomor = Pembelian::generateNomor($user->id, $noUrut, Carbon::now());

        $approverId = $this->findApprover($user, $request->gudang_id);

        DB::beginTransaction();
        try {
            $pembelian = Pembelian::create([
                'user_id' => $user->id,
                'status' => 'Pending',
                'approver_id' => $approverId,
                'no_urut_harian' => $noUrut,
                'nomor' => $nomor,
                'gudang_id' => $request->gudang_id,
                'tgl_transaksi' => $request->tgl_transaksi,
                'tgl_jatuh_tempo' => $tglJatuhTempo,
                'syarat_pembayaran' => $request->syarat_pembayaran,
                'urgensi' => $request->urgensi,
                'tahun_anggaran' => $request->tahun_anggaran,
                'tag' => $request->tag,
                'koordinat' => $request->koordinat,
                'memo' => $request->memo,
                'staf_penyetuju' => $approverId ? optional(User::find($approverId))->name : null,
                'diskon_akhir' => $totals['diskon_akhir'],
                'tax_percentage' => $totals['tax_percentage'],
                'biaya_pengiriman' => $totals['biaya_pengiriman'],
                'grand_total' => $totals['grand_total'],
                'lampiran_paths' => [],
            ]);

            foreach ($totals['items'] as $item) {
                PembelianItem::create([
                    'pembelian_id' => $pembelian->id,
                    'produk_id' => $item['produk_id'],
                    'deskripsi' => $item['deskripsi'],
                    'kuantitas' => $item['kuantitas'],
                    'unit' => $item['unit'],
                    'harga_satuan' => $item['harga_satuan'],
                    'diskon' => $item['diskon'],
                    'jumlah_baris' => $item['jumlah_baris'],
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Pembelian berhasil dibuat.', 'data' => $pembelian->load('items')], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat pembelian.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $pembelian = Pembelian::findOrFail($id);

        $coreFields = ['tgl_transaksi', 'syarat_pembayaran', 'gudang_id', 'items', 'urgensi'];
        $hasLampiran = $request->hasFile('lampiran');
        $hasCoreFields = ! empty(array_intersect(array_keys($request->all()), $coreFields));

        if ($hasLampiran && ! $hasCoreFields) {
            if ($user->role == 'user' && $pembelian->user_id != $user->id) {
                return response()->json(['message' => 'Anda hanya dapat menambah lampiran pada transaksi milik Anda sendiri.'], 403);
            }
            $lampiranPaths = $pembelian->lampiran_paths ?? [];
            $publicFolder = public_path('storage/lampiran_pembelian');
            if (! File::exists($publicFolder)) {
                File::makeDirectory($publicFolder, 0755, true);
            }
            $counter = count($lampiranPaths) + 1;
            foreach ($request->file('lampiran') as $file) {
                $filename = $pembelian->nomor.'-'.$counter.'.'.$file->getClientOriginalExtension();
                $file->move($publicFolder, $filename);
                $lampiranPaths[] = 'lampiran_pembelian/'.$filename;
                $counter++;
            }
            $pembelian->update(['lampiran_paths' => $lampiranPaths]);

            return response()->json(['message' => 'Lampiran berhasil ditambahkan.', 'data' => $pembelian->load('items')]);
        }

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah data pembelian.'], 403);
        }

        $validated = $request->validate([
            'tgl_transaksi' => 'required|date',
            'syarat_pembayaran' => 'required|string',
            'urgensi' => 'required|string',
            'gudang_id' => 'required|exists:gudangs,id',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'diskon_akhir' => 'nullable|numeric|min:0',
            'biaya_pengiriman' => 'nullable|numeric|min:0',
            'tahun_anggaran' => 'nullable',
            'tag' => 'nullable|string',
            'koordinat' => 'nullable|string',
            'memo' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.deskripsi' => 'nullable|string',
            'items.*.kuantitas' => 'required|numeric|min:1',
            'items.*.unit' => 'nullable|string',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.diskon' => 'nullable|numeric|min:0|max:100',
        ]);

        $totals = $this->calculatePurchaseTotals(
            $validated['items'],
            $validated['diskon_akhir'] ?? 0,
            $validated['tax_percentage'],
            $validated['biaya_pengiriman'] ?? 0,
        );

        $tglJatuhTempo = $this->calculateDueDate($validated['tgl_transaksi'], $validated['syarat_pembayaran']);

        try {
            $updated = DB::transaction(function () use ($pembelian, $validated, $totals, $tglJatuhTempo): Pembelian {
                $pembelian->update([
                    'gudang_id' => $validated['gudang_id'],
                    'tgl_transaksi' => $validated['tgl_transaksi'],
                    'tgl_jatuh_tempo' => $tglJatuhTempo,
                    'syarat_pembayaran' => $validated['syarat_pembayaran'],
                    'urgensi' => $validated['urgensi'],
                    'tahun_anggaran' => $validated['tahun_anggaran'] ?? null,
                    'tag' => $validated['tag'] ?? null,
                    'koordinat' => $validated['koordinat'] ?? null,
                    'memo' => $validated['memo'] ?? null,
                    'diskon_akhir' => $totals['diskon_akhir'],
                    'tax_percentage' => $totals['tax_percentage'],
                    'biaya_pengiriman' => $totals['biaya_pengiriman'],
                    'grand_total' => $totals['grand_total'],
                ]);

                $pembelian->items()->delete();

                foreach ($totals['items'] as $item) {
                    PembelianItem::create([
                        'pembelian_id' => $pembelian->id,
                        'produk_id' => $item['produk_id'],
                        'deskripsi' => $item['deskripsi'],
                        'kuantitas' => $item['kuantitas'],
                        'unit' => $item['unit'],
                        'harga_satuan' => $item['harga_satuan'],
                        'diskon' => $item['diskon'],
                        'jumlah_baris' => $item['jumlah_baris'],
                    ]);
                }

                return $pembelian->refresh()->load('items');
            });

            return response()->json(['message' => 'Pembelian berhasil diperbarui.', 'data' => $updated]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal memperbarui pembelian.'], 500);
        }
    }

    public function approve($id, HutangPostingService $hutangPostingService)
    {
        $user = auth()->user();
        $pembelian = Pembelian::findOrFail($id);

        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($pembelian->status !== 'Pending') {
            return response()->json(['message' => 'Hanya transaksi Pending yang bisa di-approve.'], 422);
        }
        if ($user->role === 'admin') {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $pembelian->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Hanya bisa approve transaksi di gudang aktif.'], 403);
            }
        }

        $pembelian = DB::transaction(function () use ($id, $user, $hutangPostingService): Pembelian {
            $lockedPurchase = Pembelian::query()->lockForUpdate()->findOrFail($id);
            if ($lockedPurchase->status !== 'Pending') {
                throw new DomainException('Hanya transaksi Pending yang bisa di-approve.');
            }

            $lockedPurchase->update(['status' => 'Approved', 'approver_id' => $user->id]);
            $hutangPostingService->postPurchase($user, $lockedPurchase->refresh());

            return $lockedPurchase->refresh();
        });

        return response()->json(['message' => 'Pembelian berhasil di-approve.', 'data' => $pembelian]);
    }

    public function cancel($id, HutangPostingService $hutangPostingService)
    {
        $pembelian = Pembelian::findOrFail($id);
        $user = auth()->user();

        if ($user->role == 'user' && $pembelian->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($user->role === 'admin') {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $pembelian->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Hanya bisa cancel transaksi di gudang aktif.'], 403);
            }
        }

        DB::transaction(function () use ($id, $user, $hutangPostingService): void {
            $lockedPurchase = Pembelian::query()->lockForUpdate()->findOrFail($id);
            if ($lockedPurchase->status !== 'Canceled' && $lockedPurchase->syarat_pembayaran !== 'Cash') {
                $hutangPostingService->reversePurchase($user, $lockedPurchase, 'Purchase canceled');
            }
            $lockedPurchase->update(['status' => 'Canceled']);
        });

        return response()->json(['message' => 'Pembelian berhasil dibatalkan.']);
    }

    public function uncancel($id)
    {
        $pembelian = Pembelian::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        }
        if ($pembelian->status !== 'Canceled') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);
        }

        $pembelian->update(['status' => 'Pending', 'approver_id' => $this->findApprover($user, $pembelian->gudang_id)]);

        return response()->json(['message' => 'Pembelian berhasil di-uncancel. Status kembali ke Pending.', 'data' => $pembelian]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function calculatePurchaseTotals(array $items, mixed $diskonAkhir, mixed $taxPercentage, mixed $biayaPengiriman): array
    {
        $calculatorItems = collect($items)
            ->map(function (array $item): array {
                $item['kuantitas'] = (string) ($item['kuantitas'] ?? 0);
                $item['harga_satuan'] = (string) ($item['harga_satuan'] ?? 0);
                $item['diskon'] = (string) ($item['diskon'] ?? 0);
                $item['diskon_nominal'] = '0';

                return $item;
            })
            ->all();

        try {
            $totals = app(PurchaseMoneyCalculator::class)->calculateTotals(
                $calculatorItems,
                (string) $diskonAkhir,
                (string) $taxPercentage,
                (string) $biayaPengiriman,
            );
        } catch (InvalidArgumentException $e) {
            $field = str_contains($e->getMessage(), 'Diskon akhir') ? 'diskon_akhir' : 'items';

            throw ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }

        $totals['items'] = collect($totals['items'])
            ->map(fn (array $item): array => [
                'produk_id' => $item['produk_id'],
                'deskripsi' => $item['deskripsi'],
                'kuantitas' => $item['kuantitas'],
                'unit' => $item['unit'],
                'harga_satuan' => $item['harga_satuan'],
                'diskon' => $item['diskon'],
                'jumlah_baris' => $item['jumlah_baris'],
            ])
            ->all();

        return $totals;
    }

    private function calculateDueDate(string $transactionDate, string $term): ?Carbon
    {
        if ($term === 'Cash') {
            return null;
        }

        $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];

        if (! isset($days[$term])) {
            return null;
        }

        return Carbon::parse($transactionDate)->addDays($days[$term]);
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
