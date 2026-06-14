<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GudangProduk;
use App\Models\Kontak;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PenjualanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Penjualan::with(['user:id,name', 'gudang:id,nama_gudang', 'approver:id,name']);

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
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor', 'like', "%{$search}%")
                    ->orWhere('pelanggan', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $penjualan = Penjualan::with(['user:id,name,no_telp', 'gudang:id,nama_gudang', 'approver:id,name', 'items.produk:id,nama_produk,item_code,satuan'])
            ->findOrFail($id);

        if ($user->role == 'user' && $penjualan->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($user->role, ['admin', 'spectator'])) {
            $currentGudang = $user->getCurrentGudang();
            if (!$currentGudang || (int) $penjualan->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Resolve phone 3-level fallback
        $resolvedPhone = '';
        if (!empty($penjualan->no_telepon)) {
            $resolvedPhone = $penjualan->no_telepon;
        } elseif (!empty($penjualan->pelanggan)) {
            $kontak = Kontak::where('nama', $penjualan->pelanggan)->first();
            if ($kontak && !empty($kontak->no_telp)) {
                $resolvedPhone = $kontak->no_telp;
            }
        }

        $json = $penjualan->toArray();
        $json['no_telepon'] = $resolvedPhone;

        return response()->json($json);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'pelanggan' => 'required|string',
            'tgl_transaksi' => 'required|date',
            'syarat_pembayaran' => 'required|string',
            'gudang_id' => 'required|exists:gudangs,id',
            'tipe_harga' => 'nullable|in:retail,grosir',
            'tax_percentage' => 'required|numeric|min:0',
            'diskon_akhir' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.kuantitas' => 'required|numeric|min:1',
            'items.*.harga_satuan' => 'nullable',
            'items.*.diskon' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_nominal' => 'nullable',
        ]);

        // Gudang access check
        if (in_array($user->role, ['admin', 'spectator'])) {
            $currentGudang = $user->getCurrentGudang();
            if (!$currentGudang || (int) $request->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && !$user->canAccessGudang($request->gudang_id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        // Validate stock
        $gudangId = $request->gudang_id;
        $stokErrors = [];

        foreach ($request->items as $item) {
            $stokGudang = GudangProduk::where('gudang_id', $gudangId)
                ->where('produk_id', $item['produk_id'])
                ->first();
            $stokTersedia = $stokGudang ? $stokGudang->stok_penjualan : 0;

            if ($stokTersedia < $item['kuantitas']) {
                $produk = Produk::find($item['produk_id']);
                $stokErrors[] = "Stok {$produk->nama_produk} tidak cukup. Tersedia: {$stokTersedia}, Diminta: {$item['kuantitas']}";
            }
        }

        if (!empty($stokErrors)) {
            return response()->json(['message' => 'Stok tidak mencukupi.', 'errors' => $stokErrors], 422);
        }

        // Calculate
        $term = $request->syarat_pembayaran;
        $tglJatuhTempo = null;
        if ($term != 'Cash') {
            $tglJatuhTempo = Carbon::parse($request->tgl_transaksi);
            $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
            if (isset($days[$term])) {
                $tglJatuhTempo->addDays($days[$term]);
            }
        }

        $tipeHarga = $request->tipe_harga ?? 'retail';
        $itemRows = $this->buildItemRows($request->items, $tipeHarga);
        $subTotal = round(array_sum(array_column($itemRows, 'jumlah_baris')), 2);
        $diskonAkhir = max(0, $this->normalizeMoneyInput($request->diskon_akhir ?? 0));
        $kenaPajak = max(0, $subTotal - $diskonAkhir);
        $pajakPersen = $request->tax_percentage ?? 0;
        $grandTotal = $kenaPajak + ($kenaPajak * ($pajakPersen / 100));

        // Generate nomor
        $countToday = Penjualan::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $nomor = Penjualan::generateNomor($user->id, $noUrut, Carbon::now());

        $approverId = $this->findApprover($user, $request->gudang_id);

        DB::beginTransaction();
        try {
            $penjualan = Penjualan::create([
                'user_id' => $user->id,
                'status' => 'Pending',
                'approver_id' => $approverId,
                'no_urut_harian' => $noUrut,
                'nomor' => $nomor,
                'gudang_id' => $request->gudang_id,
                'tipe_harga' => $tipeHarga,
                'pelanggan' => $request->pelanggan,
                'no_telepon' => $request->no_telepon,
                'alamat_penagihan' => $request->alamat_penagihan,
                'tgl_transaksi' => $request->tgl_transaksi,
                'tgl_jatuh_tempo' => $tglJatuhTempo,
                'syarat_pembayaran' => $request->syarat_pembayaran,
                'no_referensi' => $request->no_referensi,
                'tag' => $request->tag,
                'koordinat' => $request->koordinat,
                'memo' => $request->memo,
                'diskon_akhir' => $diskonAkhir,
                'tax_percentage' => $pajakPersen,
                'grand_total' => $grandTotal,
                'lampiran_paths' => [],
            ]);

            foreach ($itemRows as $itemRow) {
                PenjualanItem::create(['penjualan_id' => $penjualan->id] + $itemRow);
            }

            DB::commit();

            try {
                \App\Services\InvoiceEmailService::sendCreatedNotification($penjualan, 'penjualan');
            } catch (\Exception $e) { /* Email tidak gagalkan transaksi */ }

            try {
                \App\Services\WhatsappNotificationService::sendPenjualanCreated($penjualan);
            } catch (\Exception $e) { /* WA tidak gagalkan transaksi */ }

            return response()->json([
                'message' => 'Penjualan berhasil dibuat.',
                'data' => $penjualan->load('items'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat penjualan.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $penjualan = Penjualan::findOrFail($id);

        // Attachment-only mode detection
        $coreFields = ['pelanggan', 'tgl_transaksi', 'syarat_pembayaran', 'gudang_id', 'items'];
        $hasLampiran = $request->hasFile('lampiran');
        $hasCoreFields = !empty(array_intersect(array_keys($request->all()), $coreFields));
        $isOnlyLampiran = $hasLampiran && !$hasCoreFields;

        if ($isOnlyLampiran) {
            if ($user->role == 'user' && $penjualan->user_id != $user->id) {
                return response()->json(['message' => 'Anda hanya dapat menambah lampiran pada transaksi milik Anda sendiri.'], 403);
            }

            $lampiranPaths = $penjualan->lampiran_paths ?? [];
            $publicFolder = public_path('storage/lampiran_penjualan');
            if (!File::exists($publicFolder)) {
                File::makeDirectory($publicFolder, 0755, true);
            }
            $counter = count($lampiranPaths) + 1;
            foreach ($request->file('lampiran') as $file) {
                $extension = $file->getClientOriginalExtension();
                $filename = $penjualan->nomor . '-' . $counter . '.' . $extension;
                $file->move($publicFolder, $filename);
                $lampiranPaths[] = 'lampiran_penjualan/' . $filename;
                $counter++;
            }
            $penjualan->update(['lampiran_paths' => $lampiranPaths]);
            return response()->json(['message' => 'Lampiran berhasil ditambahkan.', 'data' => $penjualan->load('items')]);
        }

        // Full update - super admin only
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah data penjualan.'], 403);
        }

        $request->validate([
            'pelanggan' => 'required|string',
            'tgl_transaksi' => 'required|date',
            'syarat_pembayaran' => 'required|string',
            'gudang_id' => 'required|exists:gudangs,id',
            'tipe_harga' => 'nullable|in:retail,grosir',
            'tax_percentage' => 'required|numeric|min:0',
            'diskon_akhir' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.kuantitas' => 'required|numeric|min:1',
            'items.*.harga_satuan' => 'nullable',
            'items.*.diskon' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_nominal' => 'nullable',
        ]);

        // Stock validation
        $gudangId = $request->gudang_id;
        $stokErrors = [];
        foreach ($request->items as $item) {
            $stokGudang = GudangProduk::where('gudang_id', $gudangId)->where('produk_id', $item['produk_id'])->first();
            $stokTersedia = $stokGudang ? $stokGudang->stok_penjualan : 0;
            if ($stokTersedia < $item['kuantitas']) {
                $produk = Produk::find($item['produk_id']);
                $stokErrors[] = "Stok {$produk->nama_produk} tidak cukup. Tersedia: {$stokTersedia}, Diminta: {$item['kuantitas']}";
            }
        }
        if (!empty($stokErrors)) {
            return response()->json(['message' => 'Stok tidak mencukupi.', 'errors' => $stokErrors], 422);
        }

        $tipeHarga = $request->tipe_harga ?? 'retail';
        $itemRows = $this->buildItemRows($request->items, $tipeHarga);
        $subTotal = round(array_sum(array_column($itemRows, 'jumlah_baris')), 2);
        $diskonAkhir = max(0, $this->normalizeMoneyInput($request->diskon_akhir ?? 0));
        $kenaPajak = max(0, $subTotal - $diskonAkhir);
        $pajakPersen = $request->tax_percentage ?? 0;
        $grandTotal = $kenaPajak + ($kenaPajak * ($pajakPersen / 100));

        $term = $request->syarat_pembayaran;
        $tglJatuhTempo = null;
        $statusBaru = 'Pending';
        if ($term == 'Cash') {
            $statusBaru = 'Lunas';
        } else {
            $tglJatuhTempo = Carbon::parse($request->tgl_transaksi);
            $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
            if (isset($days[$term])) {
                $tglJatuhTempo->addDays($days[$term]);
            }
        }

        $approverId = $statusBaru == 'Pending' ? $this->findApprover($user, $request->gudang_id) : $penjualan->approver_id;

        DB::beginTransaction();
        try {
            $penjualan->update([
                'status' => $statusBaru,
                'approver_id' => $approverId,
                'gudang_id' => $request->gudang_id,
                'tipe_harga' => $tipeHarga,
                'pelanggan' => $request->pelanggan,
                'no_telepon' => $request->no_telepon,
                'alamat_penagihan' => $request->alamat_penagihan,
                'tgl_transaksi' => $request->tgl_transaksi,
                'tgl_jatuh_tempo' => $tglJatuhTempo,
                'syarat_pembayaran' => $request->syarat_pembayaran,
                'no_referensi' => $request->no_referensi,
                'tag' => $request->tag,
                'koordinat' => $request->koordinat,
                'memo' => $request->memo,
                'diskon_akhir' => $diskonAkhir,
                'tax_percentage' => $pajakPersen,
                'grand_total' => $grandTotal,
            ]);

            $penjualan->items()->delete();
            foreach ($itemRows as $itemRow) {
                PenjualanItem::create(['penjualan_id' => $penjualan->id] + $itemRow);
            }

            DB::commit();
            return response()->json(['message' => 'Penjualan berhasil diperbarui.', 'data' => $penjualan->load('items')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengubah penjualan.'], 500);
        }
    }

    public function approve($id)
    {
        $user = auth()->user();
        $penjualan = Penjualan::findOrFail($id);

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($penjualan->status !== 'Pending') {
            return response()->json(['message' => 'Hanya transaksi Pending yang bisa di-approve.'], 422);
        }

        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (!$currentGudang || (int) $penjualan->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Hanya bisa approve transaksi di gudang aktif.'], 403);
            }
        }

        $penjualan->update(['status' => 'Approved', 'approver_id' => $user->id]);

        try {
            \App\Services\InvoiceEmailService::sendApprovedNotification($penjualan, 'penjualan');
        } catch (\Exception $e) {}

        return response()->json(['message' => 'Penjualan berhasil di-approve.', 'data' => $penjualan]);
    }

    public function cancel($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        $user = auth()->user();

        if ($user->role == 'user' && $penjualan->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (!$currentGudang || (int) $penjualan->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Hanya bisa cancel transaksi di gudang aktif.'], 403);
            }
        }

        $penjualan->update(['status' => 'Canceled']);

        return response()->json(['message' => 'Penjualan berhasil dibatalkan.']);
    }

    public function uncancel($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        }

        if ($penjualan->status !== 'Canceled') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);
        }

        $approverId = $this->findApprover($user, $penjualan->gudang_id);
        $penjualan->update(['status' => 'Pending', 'approver_id' => $approverId]);

        return response()->json(['message' => 'Transaksi berhasil di-uncancel. Status kembali ke Pending.', 'data' => $penjualan]);
    }

    public function markAsPaid($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($penjualan->status !== 'Approved') {
            return response()->json(['message' => 'Hanya transaksi Approved yang bisa ditandai Lunas.'], 422);
        }

        if ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            if (!$currentGudang || (int) $penjualan->gudang_id !== (int) $currentGudang->id) {
                return response()->json(['message' => 'Hanya bisa ubah transaksi di gudang aktif.'], 403);
            }
        }

        $penjualan->update(['status' => 'Lunas']);

        return response()->json(['message' => 'Penjualan ditandai LUNAS.', 'data' => $penjualan]);
    }

    public function unmarkAsPaid($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat melakukan ini.'], 403);
        }

        if ($penjualan->status !== 'Lunas') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Lunas.'], 422);
        }

        $penjualan->update(['status' => 'Approved']);

        return response()->json(['message' => 'Status penjualan dikembalikan ke Approved.', 'data' => $penjualan]);
    }

    // === PRIVATE HELPERS ===

    private function buildItemRows(array $items, string $tipeHarga): array
    {
        $produkIds = collect($items)->pluck('produk_id')->filter()->values()->all();
        $produks = Produk::whereIn('id', $produkIds)->get()->keyBy('id');
        $rows = [];

        foreach ($items as $item) {
            $produk = $produks->get($item['produk_id']);
            if (!$produk) continue;

            $qty = (float) ($item['kuantitas'] ?? 0);
            $price = $this->getProdukHarga($produk, $tipeHarga);
            $discPercent = max(0, min(100, (float) ($item['diskon'] ?? 0)));
            $discNominal = max(0, $this->normalizeMoneyInput($item['diskon_nominal'] ?? 0));
            $gross = $qty * $price;
            $jumlahBaris = round(max(0, ($gross * (1 - ($discPercent / 100))) - $discNominal), 2);

            $rows[] = [
                'produk_id' => $produk->id,
                'deskripsi' => $item['deskripsi'] ?? null,
                'kuantitas' => $qty,
                'unit' => $item['unit'] ?? $produk->satuan,
                'harga_satuan' => $price,
                'diskon' => $discPercent,
                'diskon_nominal' => $discNominal,
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => $item['expired_date'] ?? null,
                'jumlah_baris' => $jumlahBaris,
            ];
        }

        return $rows;
    }

    private function getProdukHarga(Produk $produk, string $tipeHarga): float
    {
        $hargaRetail = $this->normalizeMoneyInput($produk->harga);
        $hargaGrosir = $this->normalizeMoneyInput($produk->harga_grosir ?? 0);

        if ($tipeHarga === 'grosir' && $hargaGrosir > 0) {
            return $hargaGrosir;
        }
        return $hargaRetail;
    }

    private function normalizeMoneyInput($value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $value = trim((string) ($value ?? ''));
        if ($value === '') return 0.0;

        $value = str_replace(['Rp', 'rp', ' ', "\xc2\xa0"], '', $value);
        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function findApprover($user, $gudangId): ?int
    {
        if ($user->role == 'user') {
            $admin = User::where('role', 'admin')
                ->where(function ($q) use ($gudangId) {
                    $q->where('gudang_id', $gudangId)
                        ->orWhereHas('gudangs', function ($sub) use ($gudangId) {
                            $sub->where('gudangs.id', $gudangId);
                        });
                })->first();
            return $admin ? $admin->id : optional(User::where('role', 'super_admin')->first())->id;
        }

        if ($user->role == 'admin') {
            return optional(User::where('role', 'super_admin')->first())->id;
        }

        return $user->id;
    }
}
