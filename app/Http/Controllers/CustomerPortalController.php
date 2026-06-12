<?php

namespace App\Http\Controllers;

use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\Penjualan;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    /**
     * Normalize phone number to 628xxx format.
     * Backward compatible dengan berbagai format input.
     */
    private function normalizePhone(string $phone): string
    {
        if (empty($phone)) {
            return $phone;
        }

        $phone = $this->sanitizePhone($phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '08')) {
            $phone = '62'.substr($phone, 1);
        }

        if (str_starts_with($phone, '8') && strlen($phone) >= 9 && strlen($phone) <= 13) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    private function sanitizePhone(string $phone): string
    {
        return preg_replace('/[\s\-\.\(\)]+/', '', $phone);
    }

    private function phoneCandidates(string $phone): array
    {
        $raw = $this->sanitizePhone($phone);
        $normalized = $this->normalizePhone($raw);
        $withoutPlus = ltrim($raw, '+');

        $candidates = [$raw, $withoutPlus, $normalized];

        if (str_starts_with($normalized, '62')) {
            $candidates[] = '0'.substr($normalized, 2);
            $candidates[] = substr($normalized, 2);
            $candidates[] = '+'.$normalized;
        }

        if (str_starts_with($withoutPlus, '08')) {
            $candidates[] = '62'.substr($withoutPlus, 1);
            $candidates[] = substr($withoutPlus, 1);
            $candidates[] = '+62'.substr($withoutPlus, 1);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function findKontakByPhone(string $phone): ?Kontak
    {
        return Kontak::whereIn('no_telp', $this->phoneCandidates($phone))->first();
    }

    /**
     * Step 1: Tampilkan form input nomor telepon.
     */
    public function loginForm()
    {
        if (session('customer_id')) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.login');
    }

    /**
     * Step 1: Validasi nomor telepon, arahkan ke form PIN.
     */
    public function checkPhone(Request $request)
    {
        $request->validate([
            'no_telp' => 'required|string',
        ]);

        $noTelp = $this->normalizePhone($request->no_telp);

        $kontak = $this->findKontakByPhone($request->no_telp);

        if (! $kontak) {
            return back()->with('error', 'Nomor telepon tidak terdaftar.')->withInput();
        }

        if (empty($kontak->pin)) {
            return back()->with('error', 'Akun belum diaktifkan. Hubungi sales untuk mengatur PIN.')->withInput();
        }

        return view('customer.pin', [
            'no_telp' => $noTelp,
            'nama' => $kontak->nama,
        ]);
    }

    /**
     * Step 2: Proses login dengan PIN.
     */
    public function login(Request $request)
    {
        $request->validate([
            'no_telp' => 'required|string',
            'pin' => 'required|string|size:6',
        ]);

        $noTelp = $this->normalizePhone($request->no_telp);

        $kontak = Kontak::whereIn('no_telp', $this->phoneCandidates($request->no_telp))
            ->where('pin', $request->pin)
            ->first();

        if (! $kontak) {
            return view('customer.pin', [
                'no_telp' => $noTelp,
                'nama' => $this->findKontakByPhone($request->no_telp)?->nama ?? '',
                'error' => 'PIN yang Anda masukkan salah.',
            ]);
        }

        // Session keys harus sama persis dengan lama agar backward compatible
        session([
            'customer_id' => $kontak->id,
            'customer_no_telp' => $kontak->no_telp,
            'customer_nama' => $kontak->nama,
        ]);

        return redirect()->route('customer.dashboard');
    }

    /**
     * Dashboard customer.
     */
    public function dashboard()
    {
        $kontak = Kontak::with('gudang')->findOrFail(session('customer_id'));

        $totalTransaksi = Penjualan::where('pelanggan', $kontak->nama)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->count();

        $totalNilai = Penjualan::where('pelanggan', $kontak->nama)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->sum('grand_total');

        return view('customer.dashboard', compact('kontak', 'totalTransaksi', 'totalNilai'));
    }

    /**
     * Riwayat pembelian customer.
     */
    public function history(Request $request)
    {
        $kontak = Kontak::findOrFail(session('customer_id'));

        $query = Penjualan::where('pelanggan', $kontak->nama)
            ->with(['items.produk', 'gudang', 'user'])
            ->whereIn('status', ['Approved', 'Lunas', 'Pending']);

        if ($request->filled('dari')) {
            $query->whereDate('tgl_transaksi', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tgl_transaksi', '<=', $request->sampai);
        }

        $penjualans = $query->orderBy('tgl_transaksi', 'desc')->paginate(15);

        return view('customer.history', compact('kontak', 'penjualans'));
    }

    /**
     * Detail satu transaksi.
     */
    public function historyDetail($id)
    {
        $kontak = Kontak::findOrFail(session('customer_id'));

        $penjualan = Penjualan::where('pelanggan', $kontak->nama)
            ->with(['items.produk', 'gudang', 'user'])
            ->findOrFail($id);

        return view('customer.history-detail', compact('kontak', 'penjualan'));
    }

    /**
     * Logout customer.
     */
    public function logout()
    {
        session()->forget(['customer_id', 'customer_no_telp', 'customer_nama']);

        return redirect()->route('customer.login')->with('success', 'Berhasil logout.');
    }

    /**
     * Riwayat kunjungan customer.
     */
    public function kunjungan(Request $request)
    {
        $kontak = Kontak::findOrFail(session('customer_id'));

        $query = Kunjungan::where('kontak_id', $kontak->id)
            ->with(['items.produk', 'gudang', 'user'])
            ->whereIn('status', ['Approved', 'Pending']);

        if ($request->filled('dari')) {
            $query->whereDate('tgl_kunjungan', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tgl_kunjungan', '<=', $request->sampai);
        }

        $kunjungans = $query->orderBy('tgl_kunjungan', 'desc')->paginate(15);

        return view('customer.kunjungan', compact('kontak', 'kunjungans'));
    }

    /**
     * Detail satu kunjungan.
     */
    public function kunjunganDetail($id)
    {
        $kontak = Kontak::findOrFail(session('customer_id'));

        $kunjungan = Kunjungan::where('kontak_id', $kontak->id)
            ->with(['items.produk', 'gudang', 'user'])
            ->findOrFail($id);

        return view('customer.kunjungan-detail', compact('kontak', 'kunjungan'));
    }
}
