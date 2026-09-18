<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client tipis untuk Vercel Blob REST API.
 *
 * Perilaku mengikuti @vercel/blob SDK (src: api.ts, put.ts,
 * put-helpers.ts, list.ts, del.ts, helpers.ts):
 * - Base URL default: https://vercel.com/api/blob
 * - Auth: Authorization: Bearer <BLOB_READ_WRITE_TOKEN>
 * - Header kompatibilitas: x-api-version
 * - Upload: PUT {base}/?pathname=... + header x-access, x-content-type,
 *   x-add-random-suffix, x-allow-overwrite, x-cache-control-max-age.
 * - List: GET {base}/?prefix=...&limit=...
 * - Delete: POST {base}/delete body { urls: [...] }.
 * - Baca file public: GET langsung ke `url` hasil put (tanpa token).
 *
 * File original TIDAK PERNAH diubah: bytes di-stream apa adanya ke Blob.
 */
class VercelBlobService
{
    private string $baseUrl;

    private int $apiVersion;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('blob.api_url', 'https://vercel.com/api/blob'), '/');
        $this->apiVersion = (int) config('blob.api_version', 12);
    }

    public function isConfigured(): bool
    {
        return $this->token() !== null;
    }

    public function token(): ?string
    {
        $token = (string) config('blob.token', '');

        return $token !== '' ? $token : null;
    }

    public function ordersPathname(): string
    {
        return (string) config('blob.orders_pathname', 'orders.json');
    }

    /**
     * @return array{orders: array<int,array<string,mixed>>, etag: ?string}
     */
    public function readOrders(): array
    {
        if (! $this->isConfigured()) {
            return $this->readOrdersLocalDev();
        }

        $pathname = $this->ordersPathname();
        $found = $this->findByPathname($pathname);
        if (! $found) {
            return ['orders' => [], 'etag' => null];
        }

        $response = Http::timeout(20)->get($found['url']);
        if ($response->status() === 404) {
            return ['orders' => [], 'etag' => null];
        }
        if (! $response->successful()) {
            throw new RuntimeException('Gagal membaca orders.json dari Vercel Blob (HTTP '.$response->status().').');
        }

        return ['orders' => $this->decodeOrdersJson($response->body()), 'etag' => $found['etag'] ?? null];
    }

    /**
     * @param  array<int,array<string,mixed>>  $orders
     * @return array{url: string, pathname: string, etag: ?string}
     */
    public function writeOrders(array $orders, ?string $ifMatch = null): array
    {
        $payload = json_encode(array_values($orders), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('Gagal meng-encode orders.json.');
        }

        if (! $this->isConfigured()) {
            return $this->writeOrdersLocalDev($payload);
        }

        return $this->putBlob($this->ordersPathname(), $payload, 'application/json', true, $ifMatch);
    }

    /**
     * Upload bytes ORIGINAL file ke Blob tanpa perubahan apa pun.
     *
     * @return array{url: string, downloadUrl: string, pathname: string, etag: ?string}
     */
    public function uploadOriginal(string $pathname, string $bytes, string $contentType, bool $allowOverwrite = false): array
    {
        $this->assertSafePathname($pathname);

        if (! $this->isConfigured()) {
            return $this->uploadOriginalLocalDev($pathname, $bytes, $contentType);
        }

        $result = $this->putBlob($pathname, $bytes, $contentType, $allowOverwrite);

        return [
            'url' => $result['url'],
            'downloadUrl' => $result['downloadUrl'] ?? $result['url'],
            'pathname' => $result['pathname'],
            'etag' => $result['etag'] ?? null,
        ];
    }
    public function delete(string $urlOrPathname): void
    {
        if (! $this->isConfigured()) {
            $this->deleteLocalDev($urlOrPathname);

            return;
        }

        $response = $this->blobRequest('POST', '/delete', [
            'Content-Type' => 'application/json',
        ], json_encode(['urls' => [$urlOrPathname]]));

        if (! $response->successful()) {
            Log::warning('Vercel Blob delete gagal', ['status' => $response->status()]);
        }
    }

    public function publicUrlFor(string $pathname): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $found = $this->findByPathname($pathname);

        return $found['url'] ?? null;
    }

    /**
     * @return array{url: string, downloadUrl: string, pathname: string, contentType: string, etag: ?string}
     */
    private function putBlob(string $pathname, string $body, string $contentType, bool $allowOverwrite, ?string $ifMatch = null): array
    {
        $this->assertSafePathname($pathname);
        $query = http_build_query(['pathname' => $pathname]);

        $headers = [
            'x-access' => 'public',
            'x-content-type' => $contentType,
            'x-add-random-suffix' => '0',
            'x-allow-overwrite' => $allowOverwrite ? '1' : '0',
            'x-cache-control-max-age' => '31536000',
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) strlen($body),
        ];

        if ($ifMatch !== null && $ifMatch !== '') {
            $headers['x-if-match'] = $ifMatch;
            $headers['x-allow-overwrite'] = '1';
        }

        $response = $this->blobRequest('PUT', '/?'.$query, $headers, $body);

        if ($response->status() === 412) {
            throw new RuntimeException('CONCURRENT_WRITE');
        }
        if (! $response->successful()) {
            $message = $this->apiErrorMessage($response);
            Log::error('Vercel Blob PUT gagal', ['pathname' => $pathname, 'status' => $response->status()]);
            throw new RuntimeException('Gagal menyimpan ke Vercel Blob ('.$pathname.'): '.$message);
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['url']) || empty($json['pathname'])) {
            throw new RuntimeException('Respons Vercel Blob tidak valid saat menyimpan '.$pathname.'.');
        }

        return [
            'url' => (string) $json['url'],
            'downloadUrl' => (string) ($json['downloadUrl'] ?? $json['url']),
            'pathname' => (string) $json['pathname'],
            'contentType' => (string) ($json['contentType'] ?? $contentType),
            'etag' => isset($json['etag']) ? (string) $json['etag'] : null,
        ];
    }
    /**
     * @return array{url: string, etag: ?string, pathname: string}|null
     */
    private function findByPathname(string $pathname): ?array
    {
        $query = http_build_query(['prefix' => $pathname, 'limit' => '10']);
        $response = $this->blobRequest('GET', '/?'.$query, [], null);

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Gagal membaca daftar blob (HTTP '.$response->status().').');
        }

        $json = $response->json();
        $blobs = is_array($json['blobs'] ?? null) ? $json['blobs'] : [];
        foreach ($blobs as $blob) {
            if (($blob['pathname'] ?? null) === $pathname) {
                return [
                    'url' => (string) $blob['url'],
                    'etag' => isset($blob['etag']) ? (string) $blob['etag'] : null,
                    'pathname' => $pathname,
                ];
            }
        }

        return null;
    }

    private function blobRequest(string $method, string $pathAndQuery, array $headers, ?string $body): Response
    {
        $token = $this->token();
        if (! $token) {
            throw new RuntimeException('BLOB_READ_WRITE_TOKEN belum dikonfigurasi.');
        }

        $url = $this->baseUrl.$pathAndQuery;
        $headers = array_merge([
            'Authorization' => 'Bearer '.$token,
            'x-api-version' => (string) $this->apiVersion,
        ], $headers);

        return Http::withHeaders($headers)
            ->timeout(30)
            ->withBody($body ?? '', $headers['Content-Type'] ?? 'application/json')
            ->send($method, $url);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function decodeOrdersJson(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new RuntimeException('orders.json corrupt dan tidak bisa dibaca. Data tidak diubah.');
        }
        if ($decoded !== [] && ! array_is_list($decoded)) {
            throw new RuntimeException('Format orders.json tidak valid.');
        }

        $orders = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $orderNo = (string) ($row['order_no'] ?? '');
            $photo = (string) ($row['photo'] ?? '');
            $price = $row['price'] ?? null;
            $date = (string) ($row['date'] ?? '');
            if (! preg_match('/^\d{8}$/', $orderNo)) {
                continue;
            }
            if ($photo === '' || str_contains($photo, '..')) {
                continue;
            }
            if (! is_int($price) && ! (is_string($price) && ctype_digit($price))) {
                continue;
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $orders[] = ['order_no' => $orderNo, 'photo' => $photo, 'price' => (int) $price, 'date' => $date];
        }

        return $orders;
    }

    private function assertSafePathname(string $pathname): void
    {
        if ($pathname === '' || strlen($pathname) > 950) {
            throw new RuntimeException('Pathname blob tidak valid.');
        }
        if (str_starts_with($pathname, '/') || str_contains($pathname, '..') || str_contains($pathname, '\\')) {
            throw new RuntimeException('Pathname blob tidak valid.');
        }
        foreach (["\n", "\r", '#', '?'] as $bad) {
            if (str_contains($pathname, $bad)) {
                throw new RuntimeException('Pathname blob tidak valid.');
            }
        }
    }

    private function apiErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $msg = $json['error']['message'] ?? $json['message'] ?? null;
            $code = $json['error']['code'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return $code ? $code.': '.$msg : $msg;
            }
        }

        return 'HTTP '.$response->status();
    }

    private function localDevRoot(): string
    {
        return rtrim((string) config('blob.local_dev_root'), '/');
    }

    /**
     * @return array{orders: array<int,array<string,mixed>>, etag: ?string}
     */
    private function readOrdersLocalDev(): array
    {
        $file = $this->localDevRoot().'/'.$this->ordersPathname();
        if (! is_file($file)) {
            return ['orders' => [], 'etag' => null];
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('Gagal membaca orders.json lokal.');
        }

        return ['orders' => $this->decodeOrdersJson($raw), 'etag' => sha1($raw)];
    }

    /**
     * @return array{url: string, pathname: string, etag: ?string}
     */
    private function writeOrdersLocalDev(string $payload): array
    {
        $file = $this->localDevRoot().'/'.$this->ordersPathname();
        @mkdir(dirname($file), 0777, true);
        if (@file_put_contents($file, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menulis orders.json lokal.');
        }

        return ['url' => '', 'pathname' => $this->ordersPathname(), 'etag' => sha1($payload)];
    }

    /**
     * @return array{url: string, downloadUrl: string, pathname: string, etag: ?string}
     */
    private function uploadOriginalLocalDev(string $pathname, string $bytes, string $contentType): array
    {
        $file = $this->localDevRoot().'/'.$pathname;
        @mkdir(dirname($file), 0777, true);
        if (@file_put_contents($file, $bytes, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menyimpan file lokal.');
        }

        return ['url' => '', 'downloadUrl' => '', 'pathname' => $pathname, 'etag' => sha1($bytes.$contentType)];
    }

    private function deleteLocalDev(string $urlOrPathname): void
    {
        $pathname = $urlOrPathname;
        if (str_starts_with($pathname, 'http://') || str_starts_with($pathname, 'https://')) {
            $path = parse_url($pathname, PHP_URL_PATH);
            $pathname = $path ? ltrim($path, '/') : '';
        }
        if ($pathname === '' || str_contains($pathname, '..')) {
            return;
        }
        $file = $this->localDevRoot().'/'.$pathname;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

