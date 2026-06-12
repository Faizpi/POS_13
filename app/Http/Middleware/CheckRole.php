<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $userRole = Auth::user()->role;

        // Super Admin: akses semua
        if ($userRole === 'super_admin') {
            return $next($request);
        }

        // Admin: akses rute 'admin'
        if ($userRole === 'admin' && $role === 'admin') {
            return $next($request);
        }

        // Spectator: akses rute 'admin' (read-only)
        if ($userRole === 'spectator' && $role === 'admin') {
            return $next($request);
        }

        // Role cocok persis
        if ($userRole === $role) {
            return $next($request);
        }

        return redirect('/app')->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
    }
}
