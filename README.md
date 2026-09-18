# 📋 Order Form — Laravel + Vercel Blob

```FORM UNTUK YANG NGERTI AJA, CLUE : IMG EXIF DATA EXTRACTOR ```

Aplikasi **form order** ringan berbasis **Laravel** yang dirancang untuk deploy di **Vercel**, dengan penyimpanan **Vercel Blob** — **tanpa database**.

Form order publik mengumpulkan nomor order otomatis, foto produk original, dan harga Rupiah. Semua data tersimpan sebagai `orders.json` + file foto di Vercel Blob, sehingga tetap persisten di lingkungan serverless.

---

## ✨ Fitur

* 📝 Form order publik di `/order` (boleh di-submit siapa pun)
* 🔢 Nomor order 8 digit otomatis — readonly di form, keunikan dicek ulang di server saat submit
* 📸 Upload foto produk **original** (JPG / PNG / WebP / GIF, maks 10 MB) — tanpa compress/resize/convert
* 💰 Harga Rupiah (IDR) dengan auto-format ribuan saat mengetik
* 📅 Tanggal order otomatis (zona **Asia/Jakarta**)
* 🚫 Anti double-submit — tombol loading (spinner + disabled) sampai request selesai
* ✅ Halaman sukses `/order/success` dengan nomor order final
* 🔐 Daftar order `/orders` khusus admin — login env-based tanpa database
* 🧹 Cleanup otomatis foto di Blob bila penulisan data order gagal
* 💾 Data order JSON (`orders.json`) dengan tulis kondisional ETag (retry 3x)
* 🧪 Mode local-dev fallback tanpa token (`storage/app/blob-dev/`)
* 🇮🇩🇯🇵 Antarmuka dwibahasa Indonesia + Jepang
* ▲ Siap deploy ke Vercel (runtime `vercel-php`)

---

## 🗺️ Halaman & Route

| Route | Halaman | Akses | Keterangan |
| --- | --- | --- | --- |
| `GET /` | Homepage Laravel default | Publik | Sengaja **tidak** menautkan fitur order/admin |
| `GET /order` | Form Order / 注文フォーム | Publik | Submit via `POST /order` |
| `GET /order/success` | Order Berhasil / 注文完了 | Publik* | Nomor order final dari flash session |
| `GET /admin/login` | Login Admin | Publik | Kredensial dari env, tanpa database |
| `GET /orders` | Daftar Order (admin) | Admin | Dilindungi middleware `EnsureAdminAuthenticated` |
| `POST /admin/login` · `POST /admin/logout` | — | — | Login / logout admin |
| `GET /blob-dev/{path}` | — | Dev only | Penyaji file fallback lokal; otomatis 404 bila token Blob terpasang |

\* Akses `/order/success` langsung tanpa order yang baru dibuat otomatis diarahkan kembali ke `/order`.

---

## 🧾 Isi Form Order

Form `GET /order` → `resources/views/orders/create.blade.php`:

| Field | Input | Wajib | Keterangan |
| --- | --- | --- | --- |
| **Nomor Order** (注文番号) | `order_no` | Otomatis | Readonly, preview 8 digit; nomor final dibuat ulang & dicek duplikat di server saat POST |
| **Foto Produk** (商品写真) | `photo` | ✅ | JPG / PNG / WebP / GIF, maks 10 MB; preview di browser via `URL.createObjectURL()` |
| **Harga** (価格) | `price` | ✅ | Rupiah — menerima `150000` maupun `Rp150.000`; auto-format ribuan saat mengetik |
| **Tanggal Order** (注文日) | — | Otomatis | Diisi sistem (Asia/Jakarta), tidak di-input pengguna |

Validasi server (`OrderController::store()`):

* Foto: whitelist MIME (`image/jpeg|png|webp|gif`), ekstensi berbahaya ditolak, ukuran maks 10 MB.
* Harga: diparse ke integer, rentang 1.000 – 999.999.999.999.
* Nomor order: bila bentrok dengan data existing, digenerate ulang otomatis.
* Bila penulisan data order gagal, foto yang sudah ter-upload **dibersihkan** dari Blob.

---

## 🔄 Alur Submit

```text
GET /order
    │  preview nomor 8 digit (readonly)
    ▼
POST /order ── validasi (foto, harga, nomor)
    │
    ├─ gagal ──► tetap di /order + pesan error (bisa coba lagi)
    │
    └─ sukses
        ├─ upload foto original ──► Blob items/<8digit>-<random6>.<ext>
        ├─ merge + tulis orders.json (ETag conditional, retry maks 3x)
        └─ redirect ──► GET /order/success (nomor order final)
```

---

## 🔐 Halaman Admin

* `GET /orders` menampilkan tabel: **No Order**, **Foto Items** (thumbnail — klik untuk membuka foto original), **Harga**, **Tanggal** — diurutkan dari yang terbaru. Jumlah order dan status koneksi Blob tampil di header.
* Login memakai `ADMIN_USERNAME` / `ADMIN_PASSWORD` dari environment — perbandingan timing-safe (`hash_equals`), pesan error tidak membocorkan field mana yang salah, tanpa database.
* Middleware `EnsureAdminAuthenticated` menyimpan URL tujuan (`url.intended`) agar setelah login pengguna kembali ke halaman yang diminta.

---

## 🛠️ Teknologi

| Teknologi | Peran |
| --- | --- |
| **Laravel 12** / **PHP 8.2+** | Backend & runtime |
| **Blade** | Server-side UI |
| **Tailwind CSS 4** | Styling (halaman order/admin via browser CDN build) |
| **Vercel Blob REST API** | Penyimpanan `orders.json` + foto (`app/Services/VercelBlobService.php`) |
| **Vercel** (`vercel-php@0.7.4`) | Hosting serverless |
| **JSON** | Penyimpanan data order ringan — tanpa database |

> Session/cache/queue lokal memakai `file`/`sync`, jadi fitur order tidak butuh database sama sekali. Database bawaan Laravel (sqlite) tidak dipakai oleh fitur ini.

---

## 📂 Struktur Proyek

```text
├── api/index.php                       # Entry point Vercel → public/index.php
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── OrderController.php     # Form, submit, halaman sukses, daftar order
│   │   │   └── AdminLoginController.php
│   │   └── Middleware/
│   │       └── EnsureAdminAuthenticated.php
│   └── Services/
│       └── VercelBlobService.php       # Client Vercel Blob REST API
├── config/
│   ├── admin.php                       # ADMIN_USERNAME / ADMIN_PASSWORD
│   └── blob.php                        # Token & opsi Vercel Blob
├── resources/views/
│   ├── welcome.blade.php               # Homepage default (tanpa tautan fitur)
│   ├── admin/login.blade.php           # Login admin
│   └── orders/
│       ├── layout.blade.php            # Layout bersama halaman order
│       ├── create.blade.php            # Form order
│       ├── success.blade.php           # Order berhasil
│       └── index.blade.php             # Daftar order (admin)
├── routes/web.php                      # Semua route + route dev-only /blob-dev/*
├── storage/app/blob-dev/               # Fallback lokal saat token Blob kosong
├── tests/Feature/OrderTest.php
├── vercel.json                         # Runtime + rewrite untuk Vercel
├── ORDERS_FEATURE.md                   # Dokumentasi teknis fitur order
└── composer.json
```

---

## 💾 Bentuk Data Order

`orders.json` di Vercel Blob:

```json
[
    {
        "order_no": "12345678",
        "photo": "items/12345678-aB3xY9.jpg",
        "price": 150000,
        "date": "2026-09-18"
    }
]
```

* `photo` berisi **pathname Blob**; URL publiknya dibangun saat render daftar order.
* Foto diupload apa adanya (bytes original) — tidak ada resize, compress, convert, atau optimize.
* Penulisan memakai conditional write `x-if-match` (ETag) dengan retry maks 3x untuk mengurangi risiko lost update.

---

## ⚙️ Konfigurasi Environment

Salin `.env.example` → `.env`, lalu isi:

| Variable | Wajib | Keterangan |
| --- | --- | --- |
| `APP_KEY` | ✅ | `php artisan key:generate` |
| `BLOB_READ_WRITE_TOKEN` | Production | Token Vercel Blob (Dashboard → Storage → Blob). **Kosong = mode local-dev fallback** |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | ✅ (untuk `/orders`) | Kredensial login admin, tanpa database |
| `SESSION_DRIVER=cookie` | Vercel | Wajib di serverless agar login admin bertahan antar instance |
| `BLOB_API_URL` | Opsional | Default `https://vercel.com/api/blob` |
| `BLOB_ORDERS_PATHNAME` | Opsional | Default `orders.json` |

**Mode local-dev fallback** — tanpa `BLOB_READ_WRITE_TOKEN`, data & foto disimpan ke `storage/app/blob-dev/` dan disajikan lewat route `/blob-dev/{path}`. Fallback ini hanya untuk pengembangan offline, **bukan** persistent storage di Vercel.

---

## 🚀 Menjalankan Lokal

### 1. Clone Repository

```bash
git clone https://github.com/bhottu/order-form-vercel-blob.git
cd order-form-vercel-blob
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Siapkan Environment

```bash
cp .env.example .env
php artisan key:generate
```

Untuk Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 4. Jalankan Server

```bash
php artisan serve
```

Lalu buka:

| URL | Halaman |
| --- | --- |
| `http://127.0.0.1:8000/order` | Form order (publik) |
| `http://127.0.0.1:8000/admin/login` | Login admin |
| `http://127.0.0.1:8000/orders` | Daftar order (perlu login admin) |

---

## 🧪 Testing

```bash
php artisan test --filter=OrderTest
```

Mencakup: render form + nomor order 8 digit, upload JPG/PNG original (hash bytes identik), multiple order + refresh, redirect ke `/order/success` dengan nomor benar, akses `/order/success` tanpa order kembali ke form, proteksi `/orders` + login admin, dan homepage tidak berubah.

---

## ▲ Deploy ke Vercel

1. `vercel.json` memakai runtime **`vercel-php@0.7.4`** (PHP 8.3) dan me-route semua request ke `api/index.php` yang meneruskan ke `public/index.php`. `composer install` dijalankan otomatis oleh Vercel.
2. Set Environment Variables di dashboard Vercel (Production + Preview):
   * `BLOB_READ_WRITE_TOKEN` — token Vercel Blob
   * `ADMIN_USERNAME` + `ADMIN_PASSWORD` — kredensial login `/orders`
   * `SESSION_DRIVER=cookie` — **wajib** di serverless agar login admin tidak hilang antar instance
   * Opsional: `APP_KEY`, `APP_URL`, `BLOB_ORDERS_PATHNAME`
3. Deploy:

   ```bash
   vercel --prod
   ```

4. Verifikasi: buat order di `/order`, cek `/orders`, lalu redeploy dan pastikan data tetap ada (karena tersimpan di Blob, bukan filesystem lokal).

> 🔒 Jangan pernah commit token Vercel Blob atau kredensial admin ke GitHub.

---

## 🔐 Keamanan

* Token & kredensial hanya lewat environment variable — tidak pernah di-commit.
* Login admin: perbandingan timing-safe (`hash_equals`), pesan error tidak membocorkan field yang salah, session di-regenerate saat login/logout.
* Foto: whitelist MIME, penolakan ekstensi berbahaya (`php`, `phtml`, `phar`, `exe`, `sh`, `js`, `html`, `svg`, ...), batas ukuran 10 MB.
* Nama file disimpan sebagai `items/<8digit>-<random6>.<ext>` — bukan nama file dari pengguna.
* Pathname Blob divalidasi (tanpa `..`, tanpa awalan `/`, tanpa karakter berbahaya).
* `/orders` dilindungi middleware session; route dev `/blob-dev/*` otomatis mati saat token Blob terpasang.
* CSRF token di semua form; input divalidasi di server.

---

## 🌏 Antarmuka Dwibahasa

Label utama memakai bahasa Indonesia dengan padanan bahasa Jepang yang lebih halus:

| Indonesia | Jepang |
| --- | --- |
| Form Order | 注文フォーム |
| Nomor Order | 注文番号 |
| Foto Produk | 商品写真 |
| Harga | 価格 |
| Tanggal Order | 注文日 |
| Simpan Order / Mengirim Order... | 注文を保存 / 注文を保存中... |
| Order Berhasil | 注文完了 |
| Buat Order Baru | 新しい注文を作成 |

---

## 📌 Repository

**https://github.com/bhottu/order-form-vercel-blob**

Dokumentasi teknis lengkap fitur order (detail REST API Blob, concurrency, keaslian file original): lihat **[ORDERS_FEATURE.md](ORDERS_FEATURE.md)**.

---

## 📄 License

Proyek ini di-maintain sebagai aplikasi kustom. Bila nanti didistribusikan sebagai open-source, lisensi yang sesuai dapat ditambahkan di sini.

---

<div align="center">

**Laravel × Vercel Blob**

Form order ringan tanpa database — data tersimpan di Blob.

</div>
