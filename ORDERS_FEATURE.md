# Order Items — Laravel + Vercel Blob (tanpa database)

Fitur `order/items` dengan penyimpanan persisten di **Vercel Blob**
(`orders.json` + `items/...`). Filesystem lokal Vercel tidak dipakai
sebagai persistent storage.

## Prasyarat

- PHP 8.2+, Composer
- Token Vercel Blob (Dashboard Vercel → Storage → Blob → `BLOB_READ_WRITE_TOKEN`)

## Konfigurasi lokal

```bash
cp .env.example .env
php artisan key:generate
```

Isi di `.env`:

```env
BLOB_READ_WRITE_TOKEN=vercel_blob_xxx
# Opsional:
# BLOB_API_URL=https://vercel.com/api/blob
# BLOB_ORDERS_PATHNAME=orders.json
```

Tanpa token, aplikasi berjalan dalam **mode local-dev fallback**
(`storage/app/blob-dev/`) agar bisa dikembangkan & dites offline.
Fallback ini BUKAN persistent storage di Vercel.

Session/cache/queue lokal memakai `file`/`sync` (bukan `database`)
agar fitur order tidak butuh database sama sekali.

## Menjalankan lokal

```bash
composer install
php artisan serve
```

- Form: `GET /order` (submit `POST /order`) — **publik**, bisa di-submit siapa pun.
  Field No Order kini menampilkan angka 8 digit (readonly); nomor final
  tetap dicek ulang keunikan & konfliknya di server saat POST.
  Saat submit, tombol memasuki state loading (`Mengirim Order... / 注文を保存中...`,
  spinner + disabled) sampai request selesai, sehingga tidak bisa double-submit.
  Bila gagal, pengguna tetap di `/order` dengan pesan error dan bisa mencoba lagi.
  Bila sukses, redirect ke `GET /order/success` (halaman keberhasilan yang
  menampilkan nomor order final dari flash session — tidak membuat nomor baru;
  akses langsung tanpa order baru otomatis kembali ke `/order`).
- Daftar: `GET /orders` — **khusus admin**, butuh login dulu.
- Login admin: `GET /admin/login` + `POST /admin/login`
  (kredensial dari env `ADMIN_USERNAME` / `ADMIN_PASSWORD`, tanpa database),
  logout: `POST /admin/logout`.
- Homepage `/` tidak menampilkan/menautkan fitur ini (termasuk `/admin/login`).

> Catatan Vercel: session login `file` tidak persisten antar serverless
> instance — set `SESSION_DRIVER=cookie` di Environment Variables production
> agar login admin tetap bekerja (stateless, terenkripsi via `APP_KEY`).

## Testing

```bash
php artisan test --filter=OrderTest
```

Mencakup: render form, JPG original (hash bytes sama persis),
PNG tetap PNG, multiple orders + refresh, redirect ke `/order/success`
dengan nomor order yang benar, akses `/order/success` tanpa order kembali
ke form, homepage tidak berubah.

## Deploy ke Vercel

1. Bundle PHP via `vercel-php` (community runtime):
   - `vercel.json` memakai `vercel-php@0.7.4` (versi riil terbaru per
     CHANGELOG `vercel-community/php`: 0.9.0 = PHP 8.5, 0.8.0 = PHP 8.4,
     0.7.x = PHP 8.3) dan me-route semua request ke `api/index.php`
     yang meneruskan ke `public/index.php`.
   - Pastikan build menjalankan `composer install` (Vercel otomatis bila `composer.json` ada).
2. Tambahkan Environment Variable di dashboard Vercel:
   - `BLOB_READ_WRITE_TOKEN` (Production + Preview; Development bila perlu lokal via `vercel env pull`).
   - `ADMIN_USERNAME` + `ADMIN_PASSWORD` (kredensial login /orders).
   - `SESSION_DRIVER=cookie` (wajib di serverless agar login admin tidak hilang antar instance).
   - Opsional: `APP_KEY`, `APP_URL`, `BLOB_ORDERS_PATHNAME`.
3. Deploy: `vercel --prod`.
4. Verifikasi: buka `/order`, buat order, refresh `/orders`, redeploy dan pastikan data tetap ada (karena di Blob).

## Cara kerja Vercel Blob

- Upload: `PUT https://vercel.com/api/blob/?pathname=<path>` + header
  `Authorization: Bearer <token>`, `x-api-version: 12`,
  `x-access: public`, `x-content-type`, `x-add-random-suffix: 0`,
  `x-allow-overwrite`, `x-cache-control-max-age`. Body = bytes original.
- Baca `orders.json`: `GET .../?prefix=orders.json` untuk dapat `url` + `etag`,
  lalu `GET <url>` (public, tanpa token).
- Tulis: `PUT` ulang `orders.json` + `x-allow-overwrite: 1`, dengan
  conditional write `x-if-match: <etag>` bila ada ETag (retry max 3x).
- Hapus (cleanup): `POST .../delete` body `{ urls: [...] }`.

## Keaslian file original

- `OrderController::store()` membaca `file_get_contents($file->getRealPath())`
  dan meneruskannya ke `VercelBlobService::uploadOriginal()` tanpa resize,
  compress, convert, optimize, atau perubahan format/resolusi/quality.
- Preview di browser hanya `URL.createObjectURL()` untuk UI; file yang
  di-POST tetap file asli dari `<input type="file">`.
- Filename aman: `items/<8digit>-<random6>.<ext-dari-MIME>` (bukan nama user).

## Concurrency (jujur)

- Pola read→modify→write + conditional write via `x-if-match`/ETag dengan
  retry hingga 3x mengurangi risiko lost update bila Blob mendukung 412
  precondition-failed.
- Bila Blob tidak menegakkan `x-if-match`, dua request bersamaan tetap bisa
  menimpa (last-write-wins). Tidak diklaim 100% aman; untuk skala tulis
  tinggi gunakan datastore transaksional, bukan satu file JSON.

## Struktur file fitur

```text
app/Http/Controllers/OrderController.php
app/Http/Controllers/AdminLoginController.php
app/Http/Middleware/EnsureAdminAuthenticated.php
app/Services/VercelBlobService.php
config/blob.php
config/admin.php
resources/views/orders/{layout,create,success,index}.blade.php
resources/views/admin/login.blade.php
routes/web.php (+ route dev-only /blob-dev/*)
tests/Feature/OrderTest.php
vercel.json + api/index.php
.env.example (BLOB_READ_WRITE_TOKEN + ADMIN_USERNAME/PASSWORD tanpa secret)
```

Tidak ada: database, tabel, migration baru, perubahan homepage.
