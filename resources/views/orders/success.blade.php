@extends('orders.layout', ['title' => 'Order Berhasil / 注文完了'])

@section('content')
<div class="fade-rise mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="px-6 py-12 text-center sm:px-10">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 ring-1 ring-emerald-200">
            <svg class="h-8 w-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5"></path>
            </svg>
        </div>

        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-slate-900">
            Berhasil!
            <span lang="ja" class="mt-1 block text-base font-normal text-slate-400">注文が正常に保存されました</span>
        </h1>

        <p class="mt-4 text-sm text-slate-500">Order Anda telah berhasil disimpan.</p>
        <p lang="ja" class="mt-1 text-xs text-slate-400">ご注文を保存しました。ありがとうございます。</p>

        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                Nomor Order <span lang="ja" class="ml-1.5 normal-case tracking-normal">注文番号</span>
            </p>
            <p class="mt-1.5 font-mono text-2xl font-semibold tracking-wider text-slate-900">#{{ $orderNo }}</p>
        </div>

        <a href="{{ route('orders.create') }}"
            class="mt-8 block w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            Buat Order Baru <span lang="ja" class="ml-1.5 text-xs font-normal opacity-70">新しい注文を作成</span>
        </a>
    </div>
</div>

<style>
    @keyframes fade-rise {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-rise { animation: fade-rise 0.45s ease-out both; }
    @media (prefers-reduced-motion: reduce) {
        .fade-rise { animation: none; }
    }
</style>
@endsection