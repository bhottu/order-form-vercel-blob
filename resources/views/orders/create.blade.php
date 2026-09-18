@extends('orders.layout', ['title' => 'Form Order / 注文フォーム'])

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight">Bank verification transfer <span lang="ja" class="ml-2 align-middle text-sm font-normal text-slate-400">銀行振込確認</span></h1>
                <p class="mt-1 text-sm text-slate-500">Nomor order dibuat otomatis. Foto disimpan original tanpa kompresi.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2.5 pt-0.5" role="img" aria-label="Logo Mizuho Bank">
                <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="h-11 w-11 shrink-0">
                    <rect width="44" height="44" rx="8" fill="#E60012"/>
                    <path d="M10 32V12l12 12L34 12v20" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="leading-tight">
                    <span class="block text-sm font-bold tracking-widest text-[#E60012]">MIZUHO</span>
                    <span class="block text-[11px] font-medium text-slate-600">Mizuho Bank</span>
                    <span lang="ja" class="block text-[10px] text-slate-400">みずほ銀行</span>
                </span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('orders.store') }}" enctype="multipart/form-data" class="space-y-6 px-6 py-6 sm:px-8" id="order-form">
        @csrf

        @if ($errors->has('general'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('general') }}</div>
        @endif

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div>
            <label for="order_no" class="mb-1.5 block text-sm font-medium text-slate-700">Nomor Order <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">注文番号</span></label>
            <input id="order_no" name="order_no" type="text" value="{{ old('order_no', $previewOrderNo ?? '') }}" readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 font-mono text-sm font-semibold tracking-wider text-slate-900">
            <p class="mt-1 text-xs text-slate-400">Terisi otomatis sesuai transfer order pengirim. 送金者の注文振込情報に基づいて自動入力されます</p>
        </div>

        <div>
            <label for="photo" class="mb-1.5 block text-sm font-medium text-slate-700">Foto Produk <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">商品写真</span></label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required
                class="block w-full cursor-pointer rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-700">
            @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <div class="mt-3 hidden" id="preview-wrap">
                <p class="mb-1.5 text-xs font-medium text-slate-500">Preview (hanya tampilan, file original tetap dikirim):</p>
                <img id="preview" alt="Preview foto" class="max-h-56 rounded-lg border border-slate-200 object-contain">
                <p id="preview-meta" class="mt-1 text-xs text-slate-400"></p>
            </div>
        </div>

        <div>
            <label for="price" class="mb-1.5 block text-sm font-medium text-slate-700">Harga <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">価格</span></label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                <input id="price" name="price" type="text" inputmode="numeric" autocomplete="off" required
                    placeholder="150.000" value="{{ old('price') }}"
                    class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 pl-10 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10">
            </div>
            @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-400">Harga dalam rupiah. 価格はインドネシアルピア（IDR）</p>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Order <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">注文日</span></label>
            <input type="text" value="{{ $todayLabel }} (Asia/Jakarta)" disabled
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500">
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            Kirim <span lang="ja" class="ml-1.5 text-xs font-normal opacity-70">送信</span>
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var photo = document.getElementById('photo');
    var price = document.getElementById('price');
    var wrap = document.getElementById('preview-wrap');
    var img = document.getElementById('preview');
    var meta = document.getElementById('preview-meta');

    if (photo) {
        photo.addEventListener('change', function () {
            var f = photo.files && photo.files[0];
            if (!f) { wrap.classList.add('hidden'); return; }
            // Preview via object URL saja; file yang dikirim tetap original.
            var url = URL.createObjectURL(f);
            img.src = url;
            wrap.classList.remove('hidden');
            var kb = Math.round(f.size / 1024);
            meta.textContent = f.name + ' — ' + kb.toLocaleString('id-ID') + ' KB (' + (f.type || 'tipe tidak dikenal') + ')';
        });
    }

    if (price) {
        price.addEventListener('input', function () {
            var digits = price.value.replace(/\D/g, '').slice(0, 12);
            if (!digits) { price.value = ''; return; }
            price.value = Number(digits).toLocaleString('id-ID');
        });
    }
})();
</script>
@endpush
