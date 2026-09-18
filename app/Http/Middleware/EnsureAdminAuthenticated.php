<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Halaman /orders hanya untuk admin. Tanpa database: cukup cek
     * session flag yang diset saat login admin berhasil.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_authenticated') !== true) {
            // Simpan tujuan agar setelah login kembali ke /orders.
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
