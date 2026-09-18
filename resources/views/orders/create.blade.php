@extends('orders.layout', ['title' => 'Form Order / 注文フォーム'])

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
        <h1 class="text-xl font-semibold tracking-tight">Form Order <span lang="ja" class="ml-2 align-middle text-sm font-normal text-slate-400">注文フォーム</span></h1>
        <p class="mt-1 text-sm text-slate-500">Nomor order dibuat otomatis. Harap unggah foto barang yang dipesan.</p> <span lang="ja" class="ml-2 align-middle text-sm font-normal text-slate-400">注文番号は自動的に作成されます。ご注文の商品写真をアップロードしてください。</span>
    </div>

    <form method="POST" action="{{ route('orders.store') }}" enctype="multipart/form-data" class="space-y-6 px-6 py-6 sm:px-8" id="order-form">
        @csrf

        @if ($errors->has('general'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('general') }}</div>
        @endif

        <div>
            <label for="order_no" class="mb-1.5 block text-sm font-medium text-slate-700">Nomor Order <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">注文番号</span></label>
            <input id="order_no" name="order_no" type="text" value="{{ old('order_no', $previewOrderNo ?? '') }}" readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 font-mono text-sm font-semibold tracking-wider text-slate-900">
            <p class="mt-1 text-xs text-slate-400">Nomor ini dibuat otomatis oleh sistem. この番号はシステムが自動生成します</p>
        </div>

        <div>
            <label for="photo" class="mb-1.5 block text-sm font-medium text-slate-700">Foto Produk <span lang="ja" class="ml-1.5 text-xs font-normal text-slate-400">商品写真</span></label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required
                class="block w-full cursor-pointer rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-700">
            @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <div class="mt-3 hidden" id="preview-wrap">
                <p class="mb-1.5 text-xs font-medium text-slate-500">Preview :</p>
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

        <button type="submit" id="submit-btn" aria-busy="false"
            class="flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/20 disabled:cursor-not-allowed disabled:opacity-60">
            <span id="submit-label">
                <span id="label-idle">Simpan Order <span lang="ja" class="ml-1.5 text-xs font-normal opacity-70">注文を保存</span></span>
                <span id="label-loading" class="hidden">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                        </svg>
                        <span>Mengirim Order... <span lang="ja" class="text-xs font-normal opacity-70">注文を保存中...</span></span>
                    </span>
                </span>
            </span>
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

    // Loading state saat submit: tombol disabled + spinner sampai request
    // benar-benar selesai. Mencegah double submission. Bila gagal (halaman
    // dirender ulang dengan pesan error), state reset otomatis karena
    // full page reload. CSRF & validasi server tidak berubah.
    var form = document.getElementById('order-form');
    var btn = document.getElementById('submit-btn');
    var idleLabel = document.getElementById('label-idle');
    var loadingLabel = document.getElementById('label-loading');
    var submitting = false;

    if (form && btn && idleLabel && loadingLabel) {
        form.addEventListener('submit', function (e) {
            if (submitting) { e.preventDefault(); return; }
            submitting = true;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            idleLabel.classList.add('hidden');
            loadingLabel.classList.remove('hidden');
        });

        // Reset saat kembali ke halaman ini via back/forward cache
        // (halaman bisa dipulihkan dengan tombol masih disabled).
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) { return; }
            submitting = false;
            btn.disabled = false;
            btn.setAttribute('aria-busy', 'false');
            loadingLabel.classList.add('hidden');
            idleLabel.classList.remove('hidden');
        });
    }
})();
</script>
@endpush
