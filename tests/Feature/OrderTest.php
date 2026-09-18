<?php

namespace Tests\Feature;

use App\Services\VercelBlobService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['blob.token' => null]);
        config(['blob.local_dev_root' => storage_path('framework/testing/blob-dev')]);
        config(['admin.username' => 'admin']);
        config(['admin.password' => 'secret123']);

        $root = (string) config('blob.local_dev_root');
        if (is_dir($root)) {
            $this->deleteDir($root);
        }
        @mkdir($root, 0777, true);
    }

    private function loginAsAdmin(): void
    {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'secret123',
        ])->assertRedirect('/orders');

        $this->assertTrue((bool) session('admin_authenticated'));
    }

    public function test_order_form_renders_with_order_number(): void
    {
        $response = $this->get('/order');

        $response->assertOk();
        $response->assertSee('Bank verification transfer', false);
        $response->assertSee('Mizuho Bank', false);
        $response->assertSee('MIZUHO', false);
        $response->assertSee('みずほ銀行', false);
        $response->assertSee('Form Order', false);
        $response->assertSee('注文フォーム', false);
        $response->assertSee('name="photo"', false);
        $response->assertSee('name="price"', false);
        // Field No Order tampil sebagai angka 8 digit (readonly).
        $response->assertSee('name="order_no"', false);
        $this->assertMatchesRegularExpression(
            '/name="order_no"[^>]*value="\d{8}"/',
            $response->getContent()
        );
        // Label bilingual Indonesia (utama) + Jepang (subtle).
        $response->assertSee('Nomor Order', false);
        $response->assertSee('注文番号', false);
        $response->assertSee('Foto Produk', false);
        $response->assertSee('商品写真', false);
        $response->assertSee('Harga', false);
        $response->assertSee('価格', false);
        $response->assertSee('Tanggal Order', false);
        $response->assertSee('注文日', false);
        $response->assertSee('Kirim', false);
        $response->assertSee('送信', false);
    }

    public function test_orders_requires_admin_login(): void
    {
        $this->get('/orders')->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertOk();

        // Salah password tetap ditolak.
        $this->post('/admin/login', ['username' => 'admin', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->loginAsAdmin();
        $this->get('/orders')->assertOk();
    }

    public function test_store_jpg_order_keeps_original_bytes(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ord').'.jpg';
        $img = imagecreatetruecolor(3, 2);
        imagejpeg($img, $tmp, 100);
        imagedestroy($img);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        $file = UploadedFile::fake()->createWithContent('IMG_1234.JPG', $bytes);

        $response = $this->post('/order', ['photo' => $file, 'price' => 'Rp150.000']);
        $response->assertRedirect('/orders');

        /** @var VercelBlobService $blobs */
        $blobs = app(VercelBlobService::class);
        $state = $blobs->readOrders();
        $this->assertCount(1, $state['orders']);

        $order = $state['orders'][0];
        $this->assertMatchesRegularExpression('/^\d{8}$/', $order['order_no']);
        $this->assertSame(150000, $order['price']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $order['date']);
        $this->assertMatchesRegularExpression('#^items/\d{8}-[A-Za-z0-9]+\.jpg$#', $order['photo']);

        $storedPath = (string) config('blob.local_dev_root').'/'.$order['photo'];
        $this->assertFileExists($storedPath);
        $this->assertSame(sha1($bytes), sha1_file($storedPath));
        $this->assertSame(strlen($bytes), filesize($storedPath));
    }

    public function test_store_png_order(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ord').'.png';
        $img = imagecreatetruecolor(4, 3);
        imagepng($img, $tmp);
        imagedestroy($img);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        $file = UploadedFile::fake()->createWithContent('foto.png', $bytes);
        $this->post('/order', ['photo' => $file, 'price' => '275000'])->assertRedirect('/orders');

        /** @var VercelBlobService $blobs */
        $blobs = app(VercelBlobService::class);
        $state = $blobs->readOrders();
        $this->assertCount(1, $state['orders']);
        $this->assertSame(275000, $state['orders'][0]['price']);
        $this->assertStringEndsWith('.png', $state['orders'][0]['photo']);

        $storedPath = (string) config('blob.local_dev_root').'/'.$state['orders'][0]['photo'];
        $this->assertSame(sha1($bytes), sha1_file($storedPath));
    }

    public function test_multiple_orders_all_listed_and_refresh_persists(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), 'ord').'.jpg';
            $img = imagecreatetruecolor(2 + $i, 2);
            imagejpeg($img, $tmp, 100);
            imagedestroy($img);
            $bytes = (string) file_get_contents($tmp);
            @unlink($tmp);

            $this->post('/order', [
                'photo' => UploadedFile::fake()->createWithContent("f{$i}.jpg", $bytes),
                'price' => (string) (100000 + $i * 50000),
            ])->assertRedirect('/orders');
        }

        /** @var VercelBlobService $blobs */
        $blobs = app(VercelBlobService::class);
        $state = $blobs->readOrders();
        $this->assertCount(3, $state['orders']);

        $numbers = array_column($state['orders'], 'order_no');
        $this->assertCount(3, array_unique($numbers));

        $this->loginAsAdmin();
        $response = $this->get('/orders');
        $response->assertOk();
        foreach ($numbers as $no) {
            $response->assertSee($no);
        }
        $response->assertSee('Rp150.000', false);
    }

    public function test_requested_order_number_is_used_when_unique(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ord').'.jpg';
        $img = imagecreatetruecolor(3, 2);
        imagejpeg($img, $tmp, 100);
        imagedestroy($img);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        $form = $this->get('/order')->assertOk();
        preg_match('/name="order_no"[^>]*value="(\d{8})"/', $form->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? null);

        $this->post('/order', [
            'order_no' => $m[1],
            'photo' => UploadedFile::fake()->createWithContent('a.jpg', $bytes),
            'price' => '150000',
        ])->assertRedirect('/orders');

        $state = app(VercelBlobService::class)->readOrders();
        $this->assertSame($m[1], $state['orders'][0]['order_no']);
    }

    public function test_homepage_does_not_link_order_pages(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('/order', false);
        $response->assertDontSee('/orders', false);
        $response->assertDontSee('/admin/login', false);
    }

    private function deleteDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
