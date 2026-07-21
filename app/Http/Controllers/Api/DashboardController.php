<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Biaya;
use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $now = Carbon::now();
        $data = [];

        if ($user->role == 'super_admin') {
            $pQ = Penjualan::where('status', '!=', 'Canceled');
            $bQ = Pembelian::where('status', '!=', 'Canceled');
            $biQ = Biaya::where('status', '!=', 'Canceled');
            $kQ = Kunjungan::where('status', '!=', 'Canceled');
            $data['total_produk'] = Produk::count();
            $data['total_user'] = User::count();
            $data['total_gudang'] = Gudang::count();
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            $cg = $user->getCurrentGudang();
            $gId = $cg ? $cg->id : 0;
            $pQ = Penjualan::where('status', '!=', 'Canceled')->where('gudang_id', $gId);
            $bQ = Pembelian::where('status', '!=', 'Canceled')->where('gudang_id', $gId);
            $biQ = Biaya::where('status', '!=', 'Canceled')->where('gudang_id', $gId);
            $kQ = Kunjungan::where('status', '!=', 'Canceled')->where('gudang_id', $gId);
            $data['current_gudang'] = $cg?->nama_gudang;
            $data['total_produk'] = GudangProduk::where('gudang_id', $gId)->count();
        } else {
            $pQ = Penjualan::where('status', '!=', 'Canceled')->where('user_id', $user->id);
            $bQ = Pembelian::where('status', '!=', 'Canceled')->where('user_id', $user->id);
            $biQ = Biaya::where('status', '!=', 'Canceled')->where('user_id', $user->id);
            $kQ = Kunjungan::where('status', '!=', 'Canceled')->where('user_id', $user->id);
        }

        $data['penjualan_bulan_ini'] = (clone $pQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->count();
        $data['total_penjualan_bulan_ini'] = (clone $pQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->sum('grand_total');
        $data['pembelian_bulan_ini'] = (clone $bQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->count();
        $data['total_pembelian_bulan_ini'] = (clone $bQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->sum('grand_total');
        $data['biaya_masuk_bulan_ini'] = (clone $biQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->where('jenis_biaya', 'masuk')->sum('grand_total');
        $data['biaya_keluar_bulan_ini'] = (clone $biQ)->whereMonth('tgl_transaksi', $now->month)->whereYear('tgl_transaksi', $now->year)->where('jenis_biaya', 'keluar')->sum('grand_total');
        $data['biaya_bulan_ini'] = $data['biaya_masuk_bulan_ini'] + $data['biaya_keluar_bulan_ini'];
        $data['kunjungan_bulan_ini'] = (clone $kQ)->whereMonth('tgl_kunjungan', $now->month)->whereYear('tgl_kunjungan', $now->year)->count();
        $data['pending_approval'] = (clone $pQ)->where('status', 'Pending')->count();
        $data['daily_penjualan'] = (clone $pQ)->whereDate('tgl_transaksi', $now->toDateString())->sum('grand_total');
        $data['daily_penjualan_count'] = (clone $pQ)->whereDate('tgl_transaksi', $now->toDateString())->count();

        $data['recent_penjualan'] = (clone $pQ)->with('user:id,name')->latest()->take(5)->get(['id', 'nomor', 'pelanggan', 'grand_total', 'status', 'tgl_transaksi', 'user_id', 'created_at']);
        $data['recent_kunjungan'] = (clone $kQ)->with(['kontak:id,nama', 'user:id,name'])->latest()->take(5)->get(['id', 'nomor', 'kontak_id', 'status', 'tgl_kunjungan', 'user_id', 'created_at']);

        return response()->json($data);
    }

    public function dailyReport(Request $request)
    {
        $user = auth()->user();
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'sales_name' => $user->name,
            'summary' => [
                'total_penjualan' => Penjualan::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->count(),
                'nilai_penjualan' => Penjualan::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->sum('grand_total'),
                'total_pembelian' => Pembelian::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->count(),
                'nilai_pembelian' => Pembelian::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->sum('grand_total'),
                'total_biaya' => Biaya::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->count(),
                'nilai_biaya' => Biaya::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->sum('grand_total'),
                'total_kunjungan' => Kunjungan::where('user_id', $user->id)->whereDate('tgl_kunjungan', $date)->count(),
                'total_pembayaran' => Pembayaran::where('user_id', $user->id)->whereDate('tgl_pembayaran', $date)->count(),
                'nilai_pembayaran' => Pembayaran::where('user_id', $user->id)->whereDate('tgl_pembayaran', $date)->sum('jumlah_bayar'),
            ],
            'penjualans' => Penjualan::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->get(),
            'pembelians' => Pembelian::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->get(),
            'biayas' => Biaya::where('user_id', $user->id)->whereDate('tgl_transaksi', $date)->get(),
            'kunjungans' => Kunjungan::where('user_id', $user->id)->whereDate('tgl_kunjungan', $date)->get(),
            'pembayarans' => Pembayaran::where('user_id', $user->id)->whereDate('tgl_pembayaran', $date)->get(),
        ]);
    }

    public function dailyReportPdf(Request $request)
    {
        $user = auth()->user();
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        $penjualans = Penjualan::where('user_id', $user->id)
            ->whereDate('tgl_transaksi', $date)
            ->with(['items.produk', 'gudang:id,nama_gudang'])
            ->oldest('created_at')
            ->get();

        $pembelians = Pembelian::where('user_id', $user->id)
            ->whereDate('tgl_transaksi', $date)
            ->with(['items.produk', 'gudang:id,nama_gudang'])
            ->oldest('created_at')
            ->get();

        $biayas = Biaya::where('user_id', $user->id)
            ->whereDate('tgl_transaksi', $date)
            ->with(['items', 'gudang:id,nama_gudang'])
            ->oldest('created_at')
            ->get();

        $kunjungans = Kunjungan::where('user_id', $user->id)
            ->whereDate('tgl_kunjungan', $date)
            ->with(['items.produk', 'kontak:id,nama,no_telp', 'gudang:id,nama_gudang'])
            ->oldest('created_at')
            ->get();

        $pembayarans = Pembayaran::where('user_id', $user->id)
            ->whereDate('tgl_pembayaran', $date)
            ->with(['penjualan', 'gudang:id,nama_gudang'])
            ->oldest('created_at')
            ->get();

        return Pdf::loadView('reports.daily-report', [
            'penjualans' => $penjualans,
            'pembelians' => $pembelians,
            'biayas' => $biayas,
            'kunjungans' => $kunjungans,
            'pembayarans' => $pembayarans,
            'salesName' => $user->name,
            'date' => $date->format('Y-m-d'),
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ])->setPaper('a4', 'landscape')
            ->download('Laporan-Harian-'.str_replace(' ', '_', $user->name).'-'.$date->format('Ymd').'.pdf');
    }

    public function exportOptions(Request $request)
    {
        $user = auth()->user();
        $gudangs = collect();
        $salesUsers = collect();

        if ($user->role === 'super_admin') {
            $gudangs = Gudang::orderBy('nama_gudang')->get(['id', 'nama_gudang']);
            $salesUsers = User::where('role', 'user')->orderBy('name')->get(['id', 'name', 'gudang_id']);
        } elseif ($user->role === 'admin') {
            $adminGudangIds = $user->gudangs()->pluck('gudangs.id');
            $gudangs = Gudang::whereIn('id', $adminGudangIds)->orderBy('nama_gudang')->get(['id', 'nama_gudang']);
            $salesUsers = User::where('role', 'user')->whereIn('gudang_id', $adminGudangIds)->orderBy('name')->get(['id', 'name', 'gudang_id']);
        }

        return response()->json([
            'role' => $user->role,
            'permissions' => [
                'can_export_full_report' => $user->canExportReport(),
                'can_export_pdf' => $user->canExportPdf(),
                'can_export_excel' => $user->canExportExcel(),
                'can_export_daily_pdf' => true,
                'allowed_formats' => array_filter([$user->canExportPdf() ? 'pdf' : null, $user->canExportExcel() ? 'excel' : null]),
            ],
            'transaction_types' => [
                ['value' => 'all', 'label' => 'Semua Transaksi'],
                ['value' => 'penjualan', 'label' => 'Penjualan'],
                ['value' => 'pembelian', 'label' => 'Pembelian'],
                ['value' => 'biaya', 'label' => 'Biaya'],
                ['value' => 'kunjungan', 'label' => 'Kunjungan'],
                ['value' => 'pembayaran_piutang', 'label' => 'Pembayaran Piutang'],
                ['value' => 'pembayaran_hutang', 'label' => 'Pembayaran Hutang'],
            ],
            'status_filters' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'Pending', 'label' => 'Pending'],
                ['value' => 'Approved', 'label' => 'Approved'],
                ['value' => 'Lunas', 'label' => 'Lunas'],
                ['value' => 'Rejected', 'label' => 'Rejected'],
                ['value' => 'Canceled', 'label' => 'Canceled'],
            ],
            'biaya_jenis_filters' => [
                ['value' => '', 'label' => 'Semua Jenis'],
                ['value' => 'masuk', 'label' => 'Masuk'],
                ['value' => 'keluar', 'label' => 'Keluar'],
            ],
            'tujuan_kunjungan_filters' => [
                ['value' => '', 'label' => 'Semua Tujuan'],
                ['value' => 'Pemeriksaan Stock', 'label' => 'Pemeriksaan Stock'],
                ['value' => 'Penagihan', 'label' => 'Penagihan'],
                ['value' => 'Promo', 'label' => 'Promo'],
                ['value' => 'Promo Gratis', 'label' => 'Promo Gratis'],
                ['value' => 'Promo Sample', 'label' => 'Promo Sample'],
                ['value' => 'Penawaran', 'label' => 'Penawaran'],
            ],
            'export_formats' => [['value' => 'pdf', 'label' => 'PDF'], ['value' => 'excel', 'label' => 'Excel']],
            'gudang_options' => $gudangs,
            'sales_options' => $salesUsers,
            'defaults' => ['transaction_type' => 'all', 'status_filter' => 'all', 'export_format' => 'excel'],
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $exportFormat = $request->input('export_format', 'excel');

        if (! in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['message' => 'Akses ditolak. Hanya admin/super_admin yang dapat export laporan ini.'], 403);
        }
        if ($user->role === 'admin') {
            if ($exportFormat === 'pdf' && ! $user->canExportPdf()) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk export PDF.'], 403);
            }
            if ($exportFormat === 'excel' && ! $user->canExportExcel()) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk export Excel.'], 403);
            }
            if (! $user->canExportReport()) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk export laporan.'], 403);
            }
        }

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'transaction_type' => 'required|in:all,penjualan,pembelian,biaya,kunjungan,pembayaran,pembayaran_piutang,pembayaran_hutang',
            'status_filter' => 'nullable|in:all,Pending,Approved,Rejected,Canceled,Lunas',
            'gudang_id' => 'nullable|exists:gudangs,id',
            'biaya_jenis' => 'nullable|in:masuk,keluar',
            'tujuan_filter' => 'nullable|string',
            'export_format' => 'nullable|in:excel,pdf',
            'sales_id' => 'nullable|exists:users,id',
        ]);

        $type = $request->transaction_type;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $typeLabel = match ($type) {
            'pembayaran_piutang' => 'Pembayaran_Piutang',
            'pembayaran_hutang' => 'Pembayaran_Hutang',
            default => ucfirst($type),
        };
        $fileBase = 'Laporan_'.$typeLabel.'_'.str_replace('-', '', $dateFrom).'_sd_'.str_replace('-', '', $dateTo);

        if ($request->filled('gudang_id') && $user->role === 'admin' && ! $user->canAccessGudang($request->integer('gudang_id'))) {
            return response()->json(['message' => 'Tidak memiliki akses ke gudang ini.'], 403);
        }

        $data = app(ReportExportService::class)->buildExportData(
            $user,
            $type,
            $dateFrom,
            $dateTo,
            $request->status_filter,
            $request->filled('gudang_id') ? $request->integer('gudang_id') : null,
            $request->filled('sales_id') ? $request->integer('sales_id') : null,
            $request->biaya_jenis,
            $request->tujuan_filter,
        );

        if ($exportFormat === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'transactions' => $data,
                'exportType' => $type,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'generatedBy' => $user->name,
                'generatedAt' => now()->format('d/m/Y H:i:s'),
            ])->setPaper('a4', 'landscape')->download($fileBase.'.pdf');
        }

        return Excel::download(
            new TransactionsExport($data, $type, $user->name),
            $fileBase.'.xlsx'
        );
    }

    public function downloadLampiran(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->path;
        $allowed = ['lampiran_penjualan/', 'lampiran_pembelian/', 'lampiran_biaya/', 'lampiran_kunjungan/', 'lampiran_pembayaran/', 'lampiran_penerimaan/'];
        $ok = false;
        foreach ($allowed as $p) {
            if (str_starts_with($path, $p)) {
                $ok = true;
                break;
            }
        }
        if (! $ok || str_contains($path, '..')) {
            return response()->json(['message' => 'Path tidak valid.'], 403);
        }
        $full = public_path('storage/'.$path);
        if (! file_exists($full)) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        return response()->download($full);
    }
}
