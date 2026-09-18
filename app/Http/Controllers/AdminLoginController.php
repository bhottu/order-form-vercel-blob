<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function show(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $expectedUser = (string) config('admin.username', '');
        $expectedPass = (string) config('admin.password', '');

        if ($expectedUser === '' || $expectedPass === '') {
            return back()->withInput()->withErrors([
                'username' => 'Akun admin belum dikonfigurasi (ADMIN_USERNAME / ADMIN_PASSWORD).',
            ]);
        }

        // Perbandingan timing-safe; jangan bocorkan field mana yang salah.
        $userOk = hash_equals($expectedUser, (string) $validated['username']);
        $passOk = hash_equals($expectedPass, (string) $validated['password']);

        if (! $userOk || ! $passOk) {
            return back()->withInput()->withErrors([
                'username' => 'Username atau password salah.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);

        return redirect()->intended(route('orders.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
