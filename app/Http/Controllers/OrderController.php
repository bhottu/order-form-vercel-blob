<?php

namespace App\Http\Controllers;

use App\Services\VercelBlobService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class OrderController extends Controller
{
    private const MAX_PHOTO_BYTES = 10 * 1024 * 1024; // 10 MB

    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(private VercelBlobService $blobs) {}

    public function create(): View
    {
        // Nomor order di-preview saat form dibuka (tepat 8 digit).
        // Nomor FINAL tetap dibuat ulang & dicek duplikat saat POST,
        // jadi angka preview tidak dipercaya begitu saja.
        $previewOrderNo = $this->previewOrderNo();

        return view('orders.create', [
            'todayLabel' => $this->todayLabel(),
            'blobConfigured' => $this->blobs->isConfigured(),
            'previewOrderNo' => $previewOrderNo,
        ]);
    }
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_no' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'photo' => ['required', 'file', 'max:10240'],
            'price' => ['required', 'string', 'max:32'],
        ], [
            'photo.required' => 'Foto item wajib diunggah.',
            'photo.file' => 'Foto item tidak valid.',
            'photo.max' => 'Ukuran foto maksimal 10 MB.',
            'price.required' => 'Harga wajib diisi.',
        ]);

        $price = $this->parsePrice((string) $validated['price']);
        if ($price === null) {
            return back()->withInput()->withErrors(['price' => 'Harga tidak valid. Gunakan angka Rupiah, contoh: 150000 atau Rp150.000.']);
        }

        $file = $request->file('photo');
        if (! $file || ! $file->isValid()) {
            return back()->withInput()->withErrors(['photo' => 'Upload foto gagal. Silakan coba lagi.']);
        }

        $mime = (string) $file->getMimeType();
        if (! isset(self::ALLOWED_MIMES[$mime])) {
            return back()->withInput()->withErrors(['photo' => 'Format foto harus JPG, PNG, WebP, atau GIF.']);
        }

        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        $safeExtension = $this->extensionFor($mime, $clientExtension);
        if ($safeExtension === null) {
            return back()->withInput()->withErrors(['photo' => 'Ekstensi file tidak diizinkan.']);
        }

        if ($file->getSize() === false || $file->getSize() > self::MAX_PHOTO_BYTES) {
            return back()->withInput()->withErrors(['photo' => 'Ukuran foto maksimal 10 MB.']);
        }

        // PENTING: bytes original dibaca apa adanya. Tidak ada resize /
        // compress / convert / optimize di alur ini.
        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return back()->withInput()->withErrors(['photo' => 'Gagal membaca file foto. Silakan coba lagi.']);
        }

        try {
            $state = $this->blobs->readOrders();
        } catch (RuntimeException $e) {
            Log::error('Read orders gagal', ['message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['general' => 'Gagal membaca data order: '.$e->getMessage()]);
        }

        $orders = $state['orders'];
        $usedNumbers = [];
        foreach ($orders as $row) {
            $usedNumbers[(string) ($row['order_no'] ?? '')] = true;
        }

        // Pakai angka dari form bila masih unik; jika sudah dipakai / tidak
        // dikirim, generate baru. User tidak bisa memaksa nomor duplikat.
        $requestedNo = isset($validated['order_no']) ? (string) $validated['order_no'] : '';
        if ($requestedNo !== '' && ! isset($usedNumbers[$requestedNo])) {
            $orderNo = $requestedNo;
        } else {
            $orderNo = $this->generateOrderNo($usedNumbers);
        }
        if ($orderNo === null) {
            return back()->withInput()->withErrors(['general' => 'Gagal membuat nomor order yang unik. Silakan coba lagi.']);
        }

        $pathname = 'items/'.$orderNo.'-'.Str::random(6).'.'.$safeExtension;

        try {
            $upload = $this->blobs->uploadOriginal($pathname, $bytes, $mime, false);
        } catch (RuntimeException $e) {
            Log::error('Upload foto gagal', ['pathname' => $pathname, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['photo' => 'Upload foto gagal: '.$e->getMessage()]);
        }

        $newOrder = [
            'order_no' => $orderNo,
            'photo' => $upload['pathname'],
            'price' => $price,
            'date' => $this->todayYmd(),
        ];

        $finalOrderNo = $orderNo;
        $attempts = 0;
        while (true) {
            $attempts++;
            try {
                $fresh = $this->blobs->readOrders();
                $merged = $this->mergeOrder($fresh['orders'], $newOrder);
                $this->blobs->writeOrders($merged, $fresh['etag']);
                // Nomor FINAL (bisa berubah bila mergeOrder men-regenerate
                // karena konflik) diambil dari array yang benar-benar ditulis.
                $finalOrderNo = (string) ($merged[count($merged) - 1]['order_no'] ?? $orderNo);
                break;
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'CONCURRENT_WRITE' && $attempts < 3) {
                    continue;
                }

                try {
                    $this->blobs->delete($upload['url'] !== '' ? $upload['url'] : $upload['pathname']);
                } catch (\Throwable $cleanupError) {
                    Log::warning('Cleanup foto gagal', ['pathname' => $upload['pathname']]);
                }

                Log::error('Write orders gagal', ['order_no' => $orderNo, 'message' => $e->getMessage()]);

                return back()->withInput()->withErrors(['general' => 'Order gagal disimpan dan foto sudah dibersihkan. Silakan coba lagi.']);
            }
        }

        return redirect()
            ->route('orders.success')
            ->with('order_no', $finalOrderNo);
    }

    /**
     * Halaman keberhasilan order. Nomor order diambil dari flash session
     * yang di-set saat POST /order sukses — TIDAK membuat nomor baru di sini.
     * Akses langsung tanpa order yang baru dibuat akan diarahkan ke /order.
     */
    public function success(): View|RedirectResponse
    {
        $orderNo = (string) session('order_no');
        if (! preg_match('/^\d{8}$/', $orderNo)) {
            return redirect()->route('orders.create');
        }

        return view('orders.success', ['orderNo' => $orderNo]);
    }

    public function index(VercelBlobService $blobs): View
    {
        try {
            $state = $blobs->readOrders();
            $orders = $state['orders'];
            $error = null;

            usort($orders, fn ($a, $b) => [$b['date'] ?? '', $b['order_no'] ?? ''] <=> [$a['date'] ?? '', $a['order_no'] ?? '']);

            $photoUrls = [];
            foreach ($orders as $row) {
                $pathname = (string) ($row['photo'] ?? '');
                $photoUrls[$pathname] = $this->photoUrl($blobs, $pathname);
            }
        } catch (RuntimeException $e) {
            Log::error('Read orders gagal', ['message' => $e->getMessage()]);
            $orders = [];
            $photoUrls = [];
            $error = 'Gagal memuat data order: '.$e->getMessage();
        }

        return view('orders.index', [
            'orders' => $orders,
            'photoUrls' => $photoUrls,
            'loadError' => $error ?? null,
            'blobConfigured' => $blobs->isConfigured(),
        ]);
    }
    /**
     * Parse input harga: terima "150000" maupun "Rp150.000".
     * Return integer rupiah atau null bila invalid.
     */
    public function parsePrice(string $input): ?int
    {
        $normalized = trim($input);
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^[\d\s\.,Rp]+$/i', $normalized)) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $normalized);
        if ($digits === '') {
            return null;
        }
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }
        if (strlen($digits) > 12) {
            return null;
        }

        $price = (int) $digits;
        if ($price < 1000 || $price > 999999999999) {
            return null;
        }

        return $price;
    }

    /**
     * @param  array<string,true>  $usedNumbers
     */
    public function generateOrderNo(array $usedNumbers): ?string
    {
        for ($i = 0; $i < 20; $i++) {
            $candidate = (string) random_int(10000000, 99999999);
            if (! isset($usedNumbers[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function extensionFor(string $mime, string $clientExtension): ?string
    {
        $dangerous = ['php', 'phtml', 'phar', 'exe', 'sh', 'js', 'html', 'htm', 'svg', 'xml'];
        if (in_array($clientExtension, $dangerous, true)) {
            return null;
        }

        $byMime = self::ALLOWED_MIMES[$mime];
        if ($clientExtension === 'jpeg') {
            $clientExtension = 'jpg';
        }

        $expected = ['jpg' => ['jpg', 'jpeg'], 'png' => ['png'], 'webp' => ['webp'], 'gif' => ['gif']];
        if (in_array($clientExtension, $expected[$byMime] ?? [], true)) {
            return $byMime;
        }

        return $byMime;
    }

    /**
     * @param  array<int,array<string,mixed>>  $orders
     * @param  array<string,mixed>  $newOrder
     * @return array<int,array<string,mixed>>
     */
    private function mergeOrder(array $orders, array $newOrder): array
    {
        foreach ($orders as $row) {
            if (($row['order_no'] ?? null) === $newOrder['order_no']) {
                $used = [];
                foreach ($orders as $existing) {
                    $used[(string) ($existing['order_no'] ?? '')] = true;
                }
                $fresh = $this->generateOrderNo($used);
                if ($fresh === null) {
                    throw new RuntimeException('Nomor order konflik dan tidak bisa dibuat ulang.');
                }
                $newOrder['order_no'] = $fresh;
                break;
            }
        }

        $orders[] = $newOrder;

        return array_values($orders);
    }

    private function todayYmd(): string
    {
        return Carbon::now('Asia/Jakarta')->format('Y-m-d');
    }

    /**
     * Angka 8 digit untuk ditampilkan di form. Best-effort: dicek ke
     * orders.json bila terbaca; bila Blob error, tetap tampilkan angka acak
     * karena nomor final selalu divalidasi ulang saat POST.
     */
    private function previewOrderNo(): string
    {
        try {
            $state = $this->blobs->readOrders();
            $used = [];
            foreach ($state['orders'] as $row) {
                $used[(string) ($row['order_no'] ?? '')] = true;
            }

            return $this->generateOrderNo($used) ?? (string) random_int(10000000, 99999999);
        } catch (\Throwable $e) {
            return (string) random_int(10000000, 99999999);
        }
    }

    private function todayLabel(): string
    {
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $now = Carbon::now('Asia/Jakarta');
        $month = (int) $now->format('n');

        return $now->format('j').' '.($months[$month] ?? $now->format('F')).' '.$now->format('Y');
    }

    public static function shortDateLabel(string $ymd): string
    {
        $months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];
        $parts = explode('-', $ymd);
        if (count($parts) !== 3) {
            return $ymd;
        }

        return ltrim($parts[2], '0').' '.($months[$parts[1]] ?? $parts[1]).' '.$parts[0];
    }

    private function photoUrl(VercelBlobService $blobs, string $pathname): ?string
    {
        if ($pathname === '') {
            return null;
        }

        if ($blobs->isConfigured()) {
            return $blobs->publicUrlFor($pathname);
        }

        return route('blob-dev.show', ['path' => $pathname]);
    }


}
