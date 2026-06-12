<?php

namespace App\Http\Middleware;

use App\Models\Kontak;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuth
{
    /**
     * Cek apakah customer sudah login via session (phone+PIN).
     * Session keys: customer_id, customer_no_telp, customer_nama
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('customer_id') || !session('customer_no_telp')) {
            return redirect()->route('customer.login')
                ->with('error', 'Silakan login terlebih dahulu.');
        }

        $kontak = Kontak::find(session('customer_id'));
        if (!$kontak) {
            session()->forget(['customer_id', 'customer_no_telp', 'customer_nama']);
            return redirect()->route('customer.login')
                ->with('error', 'Akun tidak ditemukan.');
        }

        // Share ke semua view customer
        view()->share('customerKontak', $kontak);

        return $next($request);
    }
}
