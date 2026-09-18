<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — {{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="mx-auto w-full max-w-md px-4 py-14 sm:px-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h1 class="text-xl font-semibold tracking-tight">Login Admin</h1>
                <p class="mt-1 text-sm text-slate-500">Halaman daftar order hanya untuk admin.</p>
            </div>

            <form method="POST" action="{{ route('admin.login.post') }}" class="space-y-5 px-6 py-6">
                @csrf

                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
                    <input id="username" name="username" type="text" autocomplete="username" required
                        value="{{ old('username') }}"
                        class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                        class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10">
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</body>
</html>
