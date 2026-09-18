<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Order' }} — {{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
        @yield('content')
        <p class="mt-10 text-center text-xs text-slate-400">Data tersimpan di Vercel Blob (orders.json). Tanpa database.</p>
    </div>
    @stack('scripts')
</body>
</html>
