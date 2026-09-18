<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vercel Blob Storage
    |--------------------------------------------------------------------------
    |
    | Konfigurasi akses ke Vercel Blob (https://vercel.com/api/blob),
    | dipakai sebagai persistent storage untuk orders.json dan foto items.
    | Filesystem lokal Vercel TIDAK boleh dipakai sebagai persistent storage.
    |
    */

    'token' => env('BLOB_READ_WRITE_TOKEN'),

    // Base URL Blob REST API (dapat dioverride untuk testing).
    // Default mengikuti @vercel/blob SDK: https://vercel.com/api/blob
    'api_url' => env('BLOB_API_URL', 'https://vercel.com/api/blob'),

    'api_version' => 12,

    // Pathname blob untuk file JSON order.
    'orders_pathname' => env('BLOB_ORDERS_PATHNAME', 'orders.json'),

    // Hanya dipakai saat BLOB_READ_WRITE_TOKEN kosong (pengembangan lokal).
    // PERINGATAN: fallback ini memakai local filesystem dan BUKAN persistent
    // storage di Vercel. Jangan mengandalkannya di production.
    'local_dev_root' => storage_path('app/blob-dev'),

];
