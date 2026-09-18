<?php

use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fitur order/items — TANPA database, storage di Vercel Blob.
// Sengaja TIDAK ditautkan dari homepage.
// /order publik (boleh di-submit siapa pun), /orders khusus admin.
Route::get('/order', [OrderController::class, 'create'])->name('orders.create');
Route::post('/order', [OrderController::class, 'store'])->name('orders.store');

// Login admin (env-based, tanpa database). Juga tidak ditautkan di homepage.
Route::get('/admin/login', [AdminLoginController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

Route::middleware(EnsureAdminAuthenticated::class)->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});

// DEV ONLY: penyaji file fallback lokal saat BLOB_READ_WRITE_TOKEN kosong.
// Route ini tidak dipakai di Vercel/production (saat token ada, photoUrl()
// mengembalikan URL Blob langsung). Jangan dipakai sebagai persistent storage.
Route::get('/blob-dev/{path}', function (string $path) {
    if (config('blob.token')) {
        abort(404);
    }
    if (str_contains($path, '..') || str_starts_with($path, '/')) {
        abort(404);
    }
    $file = rtrim((string) config('blob.local_dev_root'), '/').'/'.$path;
    if (! is_file($file)) {
        abort(404);
    }

    return response()->file($file);
})->where('path', '.*')->name('blob-dev.show');

