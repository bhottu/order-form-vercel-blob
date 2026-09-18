# 📋 Order Form — Vercel Blob

A lightweight and professional **order management form** built with **Laravel** and designed for deployment on **Vercel**.

The application is designed to collect order information, upload original product images, and manage order records using lightweight storage without requiring a traditional relational database.

---

## ✨ Features

* 📝 Professional order form
* 🔢 Automatic 8-digit order number
* 📸 Original product image upload
* 💰 Price in Indonesian Rupiah (IDR)
* 📅 Automatic order date
* 💳 Bank transfer verification information
* 💾 JSON-based order data
* ☁️ Vercel Blob image storage
* 🇮🇩 Indonesian interface
* 🇯🇵 Japanese labels alongside Indonesian
* 📋 Order management/listing page
* 🔒 No traditional relational database required
* ⚡ Laravel-based
* ▲ Vercel-ready

---

## 🛠️ Technology Stack

| Technology       | Purpose                        |
| ---------------- | ------------------------------ |
| **Laravel**      | Backend framework              |
| **PHP**          | Application runtime            |
| **Blade**        | Server-side UI                 |
| **Vercel**       | Application hosting            |
| **Vercel Blob**  | Product image storage          |
| **JSON**         | Lightweight order data storage |
| **Tailwind CSS** | UI styling                     |

---

## 📂 Project Structure

```text
order-form-vercel-blob/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env.example
├── composer.json
└── README.md
```

---

## 🧾 Order Information

Each order can contain information such as:

| Field                          | Description                               |
| ------------------------------ | ----------------------------------------- |
| **Order Number**               | Automatically generated 8-digit number    |
| **Product**                    | Product/order information                 |
| **Product Image**              | Original uploaded image                   |
| **Price**                      | Price in Indonesian Rupiah                |
| **Order Date**                 | Automatically generated date              |
| **Bank Transfer Verification** | Transfer-related verification information |

---

## ☁️ Vercel Blob

Product images are stored using **Vercel Blob** instead of relying on the local Laravel filesystem.

This is intended to make image storage suitable for a serverless deployment environment.

Uploaded product images are preserved without intentional image compression.

### Storage Concept

```text
Order Form
    │
    ├── Order Information
    │       └── JSON storage
    │
    └── Product Image
            └── Vercel Blob
```

---

## 💾 Order Data

The project uses a lightweight JSON-based approach for order records rather than a traditional relational database.

Example:

```text
orders.json
```

Conceptually:

```json
{
    "order_number": "12345678",
    "price": 150000,
    "order_date": "2026-09-18",
    "image": "https://..."
}
```

The exact structure may evolve as the application develops.

---

## 🌏 Bilingual Interface

The interface uses Indonesian as the primary language with Japanese displayed alongside important labels.

Examples:

| Indonesian                 | Japanese |
| -------------------------- | -------- |
| Form Order                 | 注文フォーム   |
| Nomor Order                | 注文番号     |
| Foto Produk                | 商品写真     |
| Harga                      | 価格       |
| Tanggal Order              | 注文日      |
| Kirim                      | 送信       |
| Bank Verification Transfer | 銀行振込確認   |

The bilingual design is intended to keep the interface professional and easy to understand.

---

## 🚀 Installation

### 1. Clone Repository

```bash
git clone https://github.com/bhottu/order-form-vercel-blob.git
cd order-form-vercel-blob
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create Environment File

```bash
cp .env.example .env
```

For Windows PowerShell, you can use:

```powershell
Copy-Item .env.example .env
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Configure Environment

Configure the required values in `.env`.

Example:

```env
APP_NAME="Order Form"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

BLOB_READ_WRITE_TOKEN=
```

### 6. Run Laravel

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

---

## ▲ Vercel Deployment

This project is designed to work with **Vercel** and **Vercel Blob**.

Before deployment, configure the required environment variables in the Vercel project settings.

Example:

```env
BLOB_READ_WRITE_TOKEN=your_vercel_blob_token
```

> Never commit your real Vercel Blob token to GitHub.

---

## 🔐 Security

Important security practices:

* Never commit `.env`
* Never expose `BLOB_READ_WRITE_TOKEN`
* Validate uploaded files
* Validate image MIME types
* Apply appropriate upload size limits
* Validate order input
* Sanitize user-provided data
* Protect private order-management pages
* Keep storage credentials server-side
* Do not place secret credentials inside frontend JavaScript

---

## 🎨 Design

The application uses a:

* Professional interface
* Clean layout
* Modern appearance
* Responsive design
* Business-oriented visual style
* Indonesian/Japanese bilingual interface
* Simple and uncluttered form

The project intentionally avoids a **hacker/terminal-style design**.

---

## 📌 Repository

GitHub:

**https://github.com/bhottu/order-form-vercel-blob**

---

## 📄 License

This project is currently maintained as a custom application.

If the project is later distributed as open-source software, an appropriate open-source license can be added here.

---

<div align="center">

**Laravel × Vercel Blob**

Built for lightweight and professional order management.

</div>
