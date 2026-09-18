@extends('orders.layout', ['title' => 'Daftar Order'])

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Daftar Order</h1>
            <p class="mt-1 text-sm text-slate-500">{{ count($orders) }} order tersimpan di Vercel Blob.</p>
        </div>
        <a href="{{ route('orders.create') }}"
           class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">+ Order Baru</a>
    </div>

    @if (session('success'))
        <div class="mx-6 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 sm:mx-8">{{ session('success') }}</div>
    @endif

    @if (! empty($loadError))
        <div class="mx-6 mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:mx-8">{{ $loadError }}</div>
    @endif

    @if (count($orders) === 0 && empty($loadError))
        <div class="px-6 py-12 text-center sm:px-8">
            <p class="text-sm font-medium text-slate-700">Belum ada order.</p>
            <p class="mt-1 text-sm text-slate-400">Buat order pertama melalui halaman input order.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                        <th class="px-6 py-3 font-medium sm:px-8">No Order</th>
                        <th class="px-4 py-3 font-medium">Foto Items</th>
                        <th class="px-4 py-3 font-medium">Harga</th>
                        <th class="px-6 py-3 font-medium sm:pr-8">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        @php
                            $pathname = (string) ($order['photo'] ?? '');
                            $url = $photoUrls[$pathname] ?? null;
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono text-[13px] font-semibold text-slate-900 sm:px-8">{{ $order['order_no'] }}</td>
                            <td class="px-4 py-4">
                                @if ($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" title="Buka foto original">
                                        <img src="{{ $url }}" alt="Foto order {{ $order['order_no'] }}" loading="lazy"
                                             class="h-16 w-16 rounded-lg border border-slate-200 object-cover transition hover:opacity-85">
                                    </a>
                                @else
                                    <span class="inline-flex h-16 w-16 items-center justify-center rounded-lg bg-slate-100 text-[10px] text-slate-400">tidak<br>ada foto</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-slate-900">Rp{{ number_format((int) $order['price'], 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-slate-500 sm:pr-8">{{ \App\Http\Controllers\OrderController::shortDateLabel((string) $order['date']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
