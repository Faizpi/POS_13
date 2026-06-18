<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PembayaranController extends Controller
{
    public function getPenjualanByGudang($gudangId)
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

        $penjualans = Penjualan::where('gudang_id', $gudangId)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->orderByDesc('tgl_transaksi')
            ->get()
            ->map(function ($p) {
                $sudahDibayar = (float) Pembayaran::where('penjualan_id', $p->id)->where('status', 'Approved')->sum('jumlah_bayar');
                $sisa = max(0, (float) ($p->grand_total ?? 0) - $sudahDibayar);
                if ($sisa <= 0) {
                    return null;
                }

                return [
                    'id' => $p->id, 'nomor' => $p->nomor ?? $p->custom_number ?? ('INV-'.$p->id),
                    'pelanggan' => $p->pelanggan ?? '-',
                    'tgl_transaksi' => optional($p->tgl_transaksi)->format('Y-m-d'),
                    'grand_total' => $p->grand_total, 'sisa_tagihan' => $sisa,
                ];
            })->filter()->values();

        return response()->json($penjualans);
    }

    public function getPenjualanDetail($id)
    {
        $user = auth()->user();
        $penjualan = Penjualan::with(['items.produk'])->findOrFail($id);

        if ($user->role == 'user' && $penjualan->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $penjualan->id, 'nomor' => $penjualan->nomor,
            'pelanggan' => $penjualan->pelanggan, 'grand_total' => $penjualan->grand_total,
            'gudang_id' => $penjualan->gudang_id,
            'items' => $penjualan->items->map(fn ($i) => [
                'produk_id' => $i->produk_id, 'nama_produk' => $i->produk?->nama_produk,
                'kuantitas' => $i->kuantitas, 'harga_satuan' => $i->harga_satuan,
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Pembayaran::with(['user:id,name', 'gudang:id,nama_gudang', 'penjualan:id,nomor,pelanggan,grand_total']);

        if ($user->role == 'super_admin') { /* all */
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) {
                $query->where('gudang_id', $cg->id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show($id)
    {
        $pembayaran = Pembayaran::with(['user:id,name', 'gudang:id,nama_gudang', 'penjualan.items', 'approver:id,name'])->findOrFail($id);

        return response()->json($pembayaran);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'penjualan_id' => 'required|exists:penjualans,id',
            'tgl_pembayaran' => 'required|date',
            'metode_pembayaran' => 'required|string',
            'jumlah_bayar' => 'required|numeric|min:1',
        ]);

        $penjualan = Penjualan::findOrFail($request->penjualan_id);

        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $penjualan->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && ! $user->canAccessGudang($penjualan->gudang_id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $countToday = Pembayaran::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
        $noUrut = $countToday + 1;
        $nomor = 'PAY-'.Carbon::now()->format('Ymd')."-{$user->id}-".str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        $pembayaran = Pembayaran::create([
            'user_id' => $user->id, 'penjualan_id' => $penjualan->id,
            'gudang_id' => $penjualan->gudang_id, 'nomor' => $nomor,
            'tgl_pembayaran' => $request->tgl_pembayaran,
            'metode_pembayaran' => $request->metode_pembayaran,
            'jumlah_bayar' => $request->jumlah_bayar,
            'keterangan' => $request->keterangan, 'status' => 'Pending',
            'lampiran_paths' => [],
        ]);

        return response()->json(['message' => 'Pembayaran berhasil dibuat.', 'data' => $pembayaran], 201);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pembayaran = Pembayaran::findOrFail($id);
        if ($pembayaran->status === 'Canceled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan, tidak bisa di-approve.'], 422);
        }
        if ($pembayaran->status === 'Approved') {
            return response()->json(['message' => 'Transaksi sudah disetujui.'], 422);
        }

        DB::beginTransaction();
        try {
            $pembayaran->update(['status' => 'Approved', 'approver_id' => $user->id]);

            $totalBayar = Pembayaran::where('penjualan_id', $pembayaran->penjualan_id)
                ->where('status', 'Approved')->sum('jumlah_bayar');

            $penjualan = $pembayaran->penjualan;
            if ($totalBayar >= $penjualan->grand_total) {
                $penjualan->update(['status' => 'Lunas']);
            }

            DB::commit();

            return response()->json(['message' => 'Pembayaran berhasil di-approve.', 'data' => $pembayaran]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal approve pembayaran.'], 500);
        }
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pembayaran = Pembayaran::findOrFail($id);
        if ($pembayaran->status === 'Canceled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }
        if ($pembayaran->status === 'Approved' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan transaksi yang sudah disetujui.'], 403);
        }

        DB::beginTransaction();
        try {
            if ($pembayaran->status === 'Approved') {
                $penjualan = $pembayaran->penjualan;
                if ($penjualan && $penjualan->status === 'Lunas') {
                    $penjualan->update(['status' => 'Approved']);
                }
            }
            $pembayaran->update(['status' => 'Canceled']);
            DB::commit();

            return response()->json(['message' => 'Pembayaran berhasil dibatalkan.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membatalkan pembayaran.'], 500);
        }
    }

    public function uncancel($id)
    {
        $pembayaran = Pembayaran::findOrFail($id);
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.'], 403);
        }
        if ($pembayaran->status !== 'Canceled') {
            return response()->json(['message' => 'Transaksi ini tidak dalam status Canceled.'], 422);
        }

        $pembayaran->update(['status' => 'Pending']);

        return response()->json(['message' => 'Pembayaran berhasil di-uncancel. Status kembali ke Pending.', 'data' => $pembayaran]);
    }

    public function indexHutang(Request $request)
    {
        $user = auth()->user();
        $query = Pembayaran::with(['user:id,name', 'gudang:id,nama_gudang', 'pembelian:id,nomor,kontak_id,grand_total', 'pembelian.kontak:id,nama'])
            ->where('type', 'hutang');

        if ($user->role == 'super_admin') { /* all */
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if ($cg) {
                $query->where('gudang_id', $cg->id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function storeHutang(Request $request)
    {
        $user = auth()->user();
        if ($user->isSpectator()) {
            return response()->json(['message' => 'Spectator tidak bisa membuat transaksi.'], 403);
        }

        $request->validate([
            'pembelian_id' => 'required|exists:pembelians,id',
            'tgl_pembayaran' => 'required|date',
            'metode_pembayaran' => 'required|string',
            'jumlah_bayar' => 'required|numeric|min:1',
        ]);

        $pembelian = Pembelian::findOrFail($request->pembelian_id);

        if (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            if (! $cg || (int) $pembelian->gudang_id !== (int) $cg->id) {
                return response()->json(['message' => 'Gudang transaksi harus sesuai gudang aktif.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && ! $user->canAccessGudang($pembelian->gudang_id)) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        DB::beginTransaction();
        try {
            $countToday = Pembayaran::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
            $noUrut = $countToday + 1;
            $nomor = 'BAYH-'.Carbon::now()->format('Ymd')."-{$user->id}-".str_pad($noUrut, 3, '0', STR_PAD_LEFT);

            $pembayaran = Pembayaran::create([
                'user_id' => $user->id, 'pembelian_id' => $pembelian->id,
                'gudang_id' => $pembelian->gudang_id, 'nomor' => $nomor,
                'type' => 'hutang',
                'tgl_pembayaran' => $request->tgl_pembayaran,
                'metode_pembayaran' => $request->metode_pembayaran,
                'jumlah_bayar' => $request->jumlah_bayar,
                'keterangan' => $request->keterangan, 'status' => 'Pending',
                'lampiran_paths' => [],
            ]);

            DB::commit();

            return response()->json(['message' => 'Pembayaran hutang berhasil dibuat.', 'data' => $pembayaran], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat pembayaran hutang.'], 500);
        }
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

        $pembelians = Pembelian::with('kontak:id,nama')
            ->where('gudang_id', $gudangId)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->whereNotNull('kontak_id')
            ->orderByDesc('tgl_transaksi')
            ->get()
            ->map(function ($p) {
                $sudahDibayar = (float) Pembayaran::where('pembelian_id', $p->id)
                    ->where('type', 'hutang')
                    ->where('status', 'Approved')
                    ->sum('jumlah_bayar');
                $sisa = max(0, (float) ($p->grand_total ?? 0) - $sudahDibayar);
                if ($sisa <= 0) {
                    return null;
                }

                return [
                    'id' => $p->id, 'nomor' => $p->nomor ?? $p->custom_number ?? ('PR-'.$p->id),
                    'kontak' => $p->kontak?->nama ?? '-',
                    'tgl_transaksi' => optional($p->tgl_transaksi)->format('Y-m-d'),
                    'grand_total' => $p->grand_total, 'sisa_hutang' => $sisa,
                ];
            })->filter()->values();

        return response()->json($pembelians);
    }

    public function getPembelianDetail($id)
    {
        $user = auth()->user();
        $pembelian = Pembelian::with(['items.produk', 'kontak:id,nama'])->findOrFail($id);

        if ($user->role == 'user' && $pembelian->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $pembelian->id, 'nomor' => $pembelian->nomor,
            'kontak' => $pembelian->kontak?->nama,
            'grand_total' => $pembelian->grand_total,
            'gudang_id' => $pembelian->gudang_id,
            'items' => $pembelian->items->map(fn ($i) => [
                'produk_id' => $i->produk_id, 'nama_produk' => $i->produk?->nama_produk,
                'kuantitas' => $i->kuantitas, 'harga_satuan' => $i->harga_satuan,
            ])->values(),
        ]);
    }

    public function exportHarianPdf(Request $request)
    {
        $request->validate([
            'tanggal' => 'nullable|date',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        $user = auth()->user();
        $defaultDate = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : Carbon::today();

        $tanggalMulai = $request->filled('tanggal_mulai')
            ? Carbon::parse($request->tanggal_mulai)->startOfDay()
            : $defaultDate->copy()->startOfDay();

        $tanggalSelesai = $request->filled('tanggal_selesai')
            ? Carbon::parse($request->tanggal_selesai)->endOfDay()
            : $defaultDate->copy()->endOfDay();

        $jatuhTempoQuery = Penjualan::with('gudang')
            ->where('status', 'Approved')
            ->whereNotNull('tgl_jatuh_tempo')
            ->whereDate('tgl_jatuh_tempo', '<=', $tanggalSelesai->toDateString());

        $this->applyPenjualanExportAccess($jatuhTempoQuery, $user);

        $jatuhTempoBelumTerbayar = $jatuhTempoQuery->orderBy('tgl_jatuh_tempo')
            ->orderBy('tgl_transaksi')
            ->get()
            ->map(fn (Penjualan $penjualan) => $this->withTagihanInfo($penjualan))
            ->filter(fn (Penjualan $penjualan) => $penjualan->jumlah_tagihan > 0)
            ->values();

        $cashQuery = Penjualan::with('gudang')
            ->whereIn('status', ['Approved', 'Lunas'])
            ->where('syarat_pembayaran', 'Cash')
            ->whereBetween('tgl_transaksi', [$tanggalMulai->toDateString(), $tanggalSelesai->toDateString()]);

        $this->applyPenjualanExportAccess($cashQuery, $user);

        $cashHariIni = $cashQuery->orderBy('tgl_transaksi')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Penjualan $penjualan) => $this->withTagihanInfo($penjualan))
            ->values();

        $totalJumlah = $jatuhTempoBelumTerbayar->sum('jumlah_tagihan');
        $totalCashHariIni = $cashHariIni->sum('grand_total');
        $totalSisaCashHariIni = $cashHariIni->sum('jumlah_tagihan');
        $totalJatuhTempo = $totalJumlah;

        return PDF::loadView('pembayaran.daily-export-pdf', [
            'invoices' => $jatuhTempoBelumTerbayar,
            'cashHariIni' => $cashHariIni,
            'jatuhTempoBelumTerbayar' => $jatuhTempoBelumTerbayar,
            'tanggal' => $defaultDate,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'generatedBy' => $user->name,
            'generatedAt' => Carbon::now(),
            'totalJumlah' => $totalJumlah,
            'totalCashHariIni' => $totalCashHariIni,
            'totalSisaCashHariIni' => $totalSisaCashHariIni,
            'totalJatuhTempo' => $totalJatuhTempo,
        ])->setPaper('a4', 'landscape')
            ->download('Tagihan-Invoice-'.$tanggalMulai->format('Ymd').'-'.$tanggalSelesai->format('Ymd').'.pdf');
    }

    private function applyPenjualanExportAccess($query, User $user)
    {
        if ($user->role === 'super_admin') {
            return $query;
        }

        if (in_array($user->role, ['admin', 'spectator'], true)) {
            $currentGudang = $user->getCurrentGudang();

            if ($currentGudang) {
                return $query->where('gudang_id', $currentGudang->id);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $user->id);
    }

    private function withTagihanInfo(Penjualan $penjualan): Penjualan
    {
        $totalBayarApproved = Pembayaran::where('penjualan_id', $penjualan->id)
            ->where('status', 'Approved')
            ->sum('jumlah_bayar');

        if ($penjualan->status === 'Lunas') {
            $penjualan->total_bayar_approved = max((float) $totalBayarApproved, (float) $penjualan->grand_total);
            $penjualan->jumlah_tagihan = 0;

            return $penjualan;
        }

        $penjualan->total_bayar_approved = $totalBayarApproved;
        $penjualan->jumlah_tagihan = max(((float) $penjualan->grand_total) - ((float) $totalBayarApproved), 0);

        return $penjualan;
    }
}
