<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GudangProduk;
use App\Models\Kunjungan;
use App\Models\KunjunganItem;
use App\Models\Produk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class KunjunganController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Kunjungan::with(['user:id,name', 'gudang:id,nama_gudang', 'kontak:id,nama']);

        if ($user->role == 'super_admin') { /* all */ }
        elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) { $query->where('gudang_id', $cg->id); }
            else { return response()->json(['data' => []]); }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $user = auth()->user();
        $kunjungan = Kunjungan::with(['user:id,name', 'gudang:id,nama_gudang', 'kontak', 'approver:id,name', 'items.produk:id,nama_produk,item_code,satuan'])->findOrFail($id);

        if ($user->role == 'user' && $kunjungan->user_id != $user->id) return response()->json(['message' => 'Unauthorized'], 403);
        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (!$cg || (int) $kunjungan->gudang_id !== (int) $cg->id) return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Transform items: add kuantitas and tipe_stok
        $derivedTipeStok = match($kunjungan->tujuan) {
            'Promo Gratis' => 'gratis',
            'Promo Sample' => 'sample',
            'Pemeriksaan Stock' => 'penjualan',
            default => null,
        };

        $kunjungan->items->transform(function ($item) use ($derivedTipeStok) {
            $item->setAttribute('kuantitas', (int) ($item->jumlah ?? 0));
            if ($derivedTipeStok) $item->setAttribute('tipe_stok', $derivedTipeStok);
            return $item;
        });

        return response()->json($kunjungan);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);

        $rules = [
            'kontak_id' => 'required|exists:kontaks,id',
            'tgl_kunjungan' => 'required|date',
            'tujuan' => 'required|in:Pemeriksaan Stock,Penagihan,Penawaran,Promo Gratis,Promo Sample',
            'sales_nama' => 'nullable|string|max:255',
            'sales_no_telepon' => 'nullable|string|max:30',
            'sales_alamat' => 'nullable|string',
            'koordinat' => 'nullable|string|max:255',
            'memo' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
        ];

        // Produk wajib hanya untuk Pemeriksaan Stock dan Promo
        if (in_array($request->tujuan, ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample'])) {
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.produk_id'] = 'required|exists:produks,id';
        } else {
            $rules['items'] = 'nullable|array';
            $rules['items.*.produk_id'] = 'nullable|exists:produks,id';
        }

        $request->validate($rules, [
            'produk_id.required' => 'Produk wajib diisi untuk kunjungan tipe ini.',
            'produk_id.min' => 'Minimal 1 produk harus dipilih.',
            'produk_id.*.required' => 'Pilih produk yang valid.',
        ]);

        // Promo stock validation
        $gudangForValidation = $user->getCurrentGudang();
        if ($gudangForValidation && in_array($request->tujuan, ['Promo Gratis', 'Promo Sample']) && $request->filled('items')) {
            $stokField = $request->tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
            $stokLabel = $request->tujuan === 'Promo Gratis' ? 'stok gratis' : 'stok sample';
            foreach ($request->items as $item) {
                if (isset($item['produk_id'])) {
                    $qty = $item['jumlah'] ?? $item['kuantitas'] ?? 1;
                    $available = GudangProduk::where('gudang_id', $gudangForValidation->id)
                        ->where('produk_id', $item['produk_id'])->value($stokField) ?? 0;
                    if ($qty > $available) {
                        $nama = Produk::find($item['produk_id'])->nama_produk ?? 'Produk';
                        return response()->json(['message' => "Qty {$nama} ({$qty}) melebihi {$stokLabel} yang tersedia ({$available})."], 422);
                    }
                }
            }
        }

        $initialStatus = 'Pending';
        $approverId = null;
        $gudangId = $request->gudang_id ?? optional($user->getCurrentGudang())->id;

        if ($user->role == 'super_admin') {
            $initialStatus = 'Approved';
            $approverId = $user->id;
        } else {
            if ($user->role === 'admin') {
                $cg = $user->getCurrentGudang();
                if ($cg && $gudangId && (int) $gudangId !== (int) $cg->id) {
                    return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
                }
                $gudangId = $gudangId ?? optional($cg)->id;
            }
            $approverId = optional(User::where('role', 'super_admin')->first())->id;
        }

        $countToday = Kunjungan::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
        $noUrut = $countToday + 1;
        $nomor = "VST-" . Carbon::now()->format('Ymd') . "-{$user->id}-" . str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $kunjungan = Kunjungan::create([
                'user_id' => $user->id, 'kontak_id' => $request->kontak_id,
                'gudang_id' => $gudangId, 'nomor' => $nomor, 'no_urut_harian' => $noUrut,
                'sales_nama' => $request->sales_nama ?? $user->name,
                'sales_no_telepon' => $request->sales_no_telepon ?? $user->no_telp,
                'sales_alamat' => $request->sales_alamat ?? $user->alamat,
                'tgl_kunjungan' => $request->tgl_kunjungan,
                'tujuan' => $request->tujuan,
                'koordinat' => $request->koordinat, 'memo' => $request->memo,
                'status' => $initialStatus, 'approver_id' => $approverId,
                'lampiran_paths' => [],
            ]);

            if ($request->filled('items')) {
                foreach ($request->items as $item) {
                    if (isset($item['produk_id'])) {
                        KunjunganItem::create([
                            'kunjungan_id' => $kunjungan->id,
                            'produk_id' => $item['produk_id'],
                            'jumlah' => $item['jumlah'] ?? $item['kuantitas'] ?? 1,
                            'batch_number' => $item['batch_number'] ?? null,
                            'expired_date' => $item['expired_date'] ?? null,
                            'keterangan' => $item['keterangan'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Kunjungan berhasil dibuat.', 'data' => $kunjungan->load('items')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat kunjungan.'], 500);
        }
    }

    public function update(Request $request, $id) {
        $user = auth()->user();
        $kunjungan = Kunjungan::findOrFail($id);

        // Pengecualian Khusus Lampiran: Boleh diakses pemilik transaksi jika hanya upload lampiran
        // Deteksi: ada file lampiran DAN tidak ada field data transaksi utama
        $coreFields = ['kontak_id', 'tgl_kunjungan', 'tujuan', 'items', 'sales_nama'];
        $hasLampiran = $request->hasFile('lampiran');
        $hasCoreFields = !empty(array_intersect(array_keys($request->all()), $coreFields));
        $isOnlyLampiran = $hasLampiran && !$hasCoreFields;

        if ($isOnlyLampiran) {
            if ($user->role == 'user' && $kunjungan->user_id != $user->id) {
                return response()->json(['message' => 'Anda hanya dapat menambah lampiran pada transaksi milik Anda sendiri.'], 403);
            }

            if (!$hasLampiran) {
                return response()->json(['message' => 'File lampiran tidak valid atau ukurannya terlalu besar (Maksimal 2MB).'], 422);
            }

            $lampiranPaths = $kunjungan->lampiran_paths ?? [];
            $publicFolder = public_path('storage/lampiran_kunjungan');
            if (!File::exists($publicFolder)) {
                File::makeDirectory($publicFolder, 0755, true);
            }
            $counter = count($lampiranPaths) + 1;
            foreach ($request->file('lampiran') as $file) {
                $extension = $file->getClientOriginalExtension();
                $filename = $kunjungan->nomor . '-' . $counter . '.' . $extension;
                $file->move($publicFolder, $filename);
                $lampiranPaths[] = 'lampiran_kunjungan/' . $filename;
                $counter++;
            }
            $kunjungan->update(['lampiran_paths' => $lampiranPaths]);
            return response()->json(['message' => 'Lampiran berhasil ditambahkan.', 'data' => $kunjungan->load('items')]);
        }

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah data kunjungan.'], 403);
        }

        $request->validate([
            'kontak_id' => 'required|exists:kontaks,id',
            'sales_nama' => 'required|string|max:255',
            'sales_no_telepon' => 'nullable|string|max:30',
            'sales_alamat' => 'nullable|string',
            'tujuan' => 'required|in:Pemeriksaan Stock,Penagihan,Penawaran,Promo Gratis,Promo Sample',
            'memo' => 'nullable|string',
            'koordinat' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*.produk_id' => 'exists:produks,id',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
        ]);

        // Produk wajib hanya untuk Pemeriksaan Stock dan Promo
        if (in_array($request->tujuan, ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample'])) {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.produk_id' => 'required|exists:produks,id',
            ], [
                'items.required' => 'Produk wajib diisi untuk kunjungan tipe ini.',
                'items.min' => 'Minimal 1 produk harus dipilih.',
                'items.*.produk_id.required' => 'Pilih produk yang valid.',
            ]);
        }

        // Handle lampiran append
        $lampiranPaths = $kunjungan->lampiran_paths ?? [];
        if ($request->hasFile('lampiran')) {
            $publicFolder = public_path('storage/lampiran_kunjungan');
            if (!File::exists($publicFolder)) {
                File::makeDirectory($publicFolder, 0755, true);
            }
            $counter = count($lampiranPaths) + 1;
            foreach ($request->file('lampiran') as $file) {
                $extension = $file->getClientOriginalExtension();
                $filename = $kunjungan->nomor . '-' . $counter . '.' . $extension;
                $file->move($publicFolder, $filename);
                $lampiranPaths[] = 'lampiran_kunjungan/' . $filename;
                $counter++;
            }
        }

        // Validasi stok untuk Promo Gratis dan Promo Sample
        $gudangForValidation = $kunjungan->gudang_id ? \App\Models\Gudang::find($kunjungan->gudang_id) : $user->getCurrentGudang();
        if ($gudangForValidation && in_array($request->tujuan, ['Promo Gratis', 'Promo Sample']) && $request->filled('items')) {
            $stokField = $request->tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
            $stokLabel = $request->tujuan === 'Promo Gratis' ? 'stok gratis' : 'stok sample';
            foreach ($request->items as $item) {
                if (isset($item['produk_id'])) {
                    $qty = $item['jumlah'] ?? $item['kuantitas'] ?? 1;
                    $stokAvailable = GudangProduk::where('gudang_id', $gudangForValidation->id)
                        ->where('produk_id', $item['produk_id'])
                        ->value($stokField) ?? 0;
                    if ($qty > $stokAvailable) {
                        $namaProduk = Produk::find($item['produk_id'])->nama_produk ?? 'Produk';
                        return response()->json([
                            'message' => "Qty {$namaProduk} ({$qty}) melebihi {$stokLabel} yang tersedia ({$stokAvailable})."
                        ], 422);
                    }
                }
            }
        }

        $kunjungan->update([
            'kontak_id' => $request->kontak_id,
            'sales_nama' => $request->sales_nama,
            'sales_no_telepon' => $request->sales_no_telepon,
            'sales_alamat' => $request->sales_alamat,
            'tujuan' => $request->tujuan,
            'koordinat' => $request->koordinat,
            'memo' => $request->memo,
            'lampiran_paths' => $lampiranPaths,
        ]);

        // Update items: hapus lama, buat baru
        $kunjungan->items()->delete();
        if ($request->filled('items')) {
            foreach ($request->items as $item) {
                if (isset($item['produk_id'])) {
                    KunjunganItem::create([
                        'kunjungan_id' => $kunjungan->id,
                        'produk_id' => $item['produk_id'],
                        'jumlah' => $item['jumlah'] ?? $item['kuantitas'] ?? 1,
                        'batch_number' => $item['batch_number'] ?? null,
                        'expired_date' => $item['expired_date'] ?? null,
                        'keterangan' => $item['keterangan'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Kunjungan berhasil diperbarui.', 'data' => $kunjungan->load('items')]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'super_admin'])) return response()->json(['message' => 'Unauthorized'], 403);

        $kunjungan = Kunjungan::findOrFail($id);
        if ($kunjungan->status !== 'Pending') return response()->json(['message' => 'Hanya transaksi Pending yang bisa di-approve.'], 422);

        // Check authorization: super_admin always can, admin if gudang matches or is approver
        $canApprove = false;
        if ($user->isSuperAdmin()) {
            $canApprove = true;
        } elseif ($user->role === 'admin') {
            $currentGudang = $user->getCurrentGudang();
            // Admin bisa approve jika:
            // 1. Kunjuangan punya approver_id yang sama dengan user (approver yang ditunjuk)
            // 2. Atau user punya akses ke gudang kunjungan (current_gudang_id atau gudang_id match)
            if ($kunjungan->approver_id == $user->id) {
                $canApprove = true;
            } elseif ($currentGudang && 
                     ((int)$kunjungan->gudang_id === (int)$currentGudang->id ||
                      $user->gudangs()->where('gudangs.id', $kunjungan->gudang_id)->exists())) {
                $canApprove = true;
            }
        }

        if (!$canApprove) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menyetujui kunjungan ini.'], 403);
        }

        $kunjungan->update(['status' => 'Approved', 'approver_id' => $user->id]);
        return response()->json(['message' => 'Kunjungan berhasil di-approve.', 'data' => $kunjungan]);
    }

    public function cancel($id)
    {
        $user = auth()->user();
        $kunjungan = Kunjungan::findOrFail($id);

        if ($kunjungan->status === 'Canceled') return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);

        // Super admin bisa cancel kapan saja
        if ($user->isSuperAdmin()) {
            $kunjungan->update(['status' => 'Canceled']);
            return response()->json(['message' => 'Kunjungan berhasil dibatalkan.']);
        }

        // Admin hanya bisa cancel jika status Pending
        if ($user->role === 'admin') {
            $cg = $user->getCurrentGudang();
            if (!$cg || (int)$kunjungan->gudang_id !== (int)$cg->id) {
                return response()->json(['message' => 'Hanya bisa cancel transaksi di gudang aktif.'], 403);
            }
            if ($kunjungan->status !== 'Pending') {
                return response()->json(['message' => 'Hanya bisa cancel transaksi Pending.'], 403);
            }
            $kunjungan->update(['status' => 'Canceled']);
            return response()->json(['message' => 'Kunjungan berhasil dibatalkan.']);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function uncancel($id)
    {
        $kunjungan = Kunjungan::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin()) return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        if ($kunjungan->status !== 'Canceled') return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);

        // Tentukan approver berdasarkan gudang transaksi (seperti web controller)
        $gudangId = $kunjungan->gudang_id;
        $approverId = $user->id; // fallback

        if ($gudangId) {
            // Cari admin yang handle gudang ini
            $adminGudang = User::where('role', 'admin')
                ->where(function ($q) use ($gudangId) {
                    $q->where('gudang_id', $gudangId)
                        ->orWhere('current_gudang_id', $gudangId)
                        ->orWhereHas('gudangs', function ($sub) use ($gudangId) {
                            $sub->where('gudangs.id', $gudangId);
                        });
                })
                ->first();

            if ($adminGudang) {
                $approverId = $adminGudang->id;
            }
        }

        $kunjungan->update(['status' => 'Pending', 'approver_id' => $approverId]);
        return response()->json(['message' => 'Kunjungan berhasil di-uncancel. Status kembali ke Pending.', 'data' => $kunjungan]);
    }
}
