<?php

namespace Tests;

use App\Services\SafeImageUpload;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

final class SafeImageUploadTest extends CIUnitTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFklEQVQImWP8z8DAwMDAxMDAwMDAAAANHQEDDMfniQAAAABJRU5ErkJggg==';
    private string $root;
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        \Config\Services::resetSingle('image');
        $this->root = sys_get_temp_dir() . '/kartar_image_' . bin2hex(random_bytes(8)) . '/';
        mkdir($this->root, 0700);
        $this->source = $this->root . 'synthetic-source';
        file_put_contents($this->source, base64_decode(self::PNG) . '<?php synthetic-private-marker ?>');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . 'uploads/users/profile/*') ?: [] as $file) unlink($file);
        foreach (['uploads/users/profile', 'uploads/users', 'uploads'] as $directory) {
            if (is_dir($this->root . $directory)) rmdir($this->root . $directory);
        }
        if (is_file($this->source)) unlink($this->source);
        rmdir($this->root);
        \Config\Services::resetSingle('image');
        parent::tearDown();
    }

    public function test_encoder_failure_retains_source_and_cleans_partial_output(): void
    {
        $image = $this->getMockBuilder(\CodeIgniter\Images\Handlers\GDHandler::class)
            ->disableOriginalConstructor()->onlyMethods(['withFile', 'fit', 'convert', 'save'])->getMock();
        foreach (['withFile', 'fit', 'convert'] as $method) $image->method($method)->willReturnSelf();
        $image->method('save')->willReturnCallback(static function ($target) {
            file_put_contents($target, 'partial-synthetic-private-marker');
            throw new \RuntimeException('synthetic-encoder-private-detail');
        });
        \Config\Services::injectMock('image', $image);
        try {
            SafeImageUpload::store(new UploadedFile($this->source, 'synthetic.php'), 'uploads/users/profile/', true, $this->root);
            $this->fail('Failed encoder must not move raw upload');
        } catch (\RuntimeException $error) {
            $this->assertSame('Image cannot be processed', $error->getMessage());
            $this->assertFileExists($this->source);
            $this->assertSame([], glob($this->root . 'uploads/users/profile/*'));
        }
    }

    public function test_actual_encoder_strips_trailing_content_and_client_script_extension(): void
    {
        if (!extension_loaded('gd')) $this->markTestSkipped('Native GD verification requires php -d extension=gd');
        $relative = SafeImageUpload::store(new UploadedFile($this->source, 'synthetic.php'), 'uploads/users/profile/', true, $this->root);
        $this->assertMatchesRegularExpression('#^uploads/users/profile/[a-f0-9]{32}\.png$#', $relative);
        $this->assertStringNotContainsString('synthetic-private-marker', file_get_contents($this->root . $relative));
        $image = getimagesize($this->root . $relative);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame([512, 512], [$image[0], $image[1]]);
        $this->assertFileExists($this->source);
    }

    public function test_non_image_is_rejected_before_file_or_encoder_write(): void
    {
        file_put_contents($this->source, '<?php synthetic-private-marker ?>');
        try {
            SafeImageUpload::store(new UploadedFile($this->source, 'synthetic.png'), 'uploads/users/profile/', true, $this->root);
            $this->fail('Non-image must fail closed');
        } catch (\RuntimeException $error) {
            $this->assertFileExists($this->source);
            $this->assertDirectoryDoesNotExist($this->root . 'uploads');
        }
    }

    public function test_oversized_dimensions_are_rejected_before_decode(): void
    {
        $png = base64_decode(self::PNG);
        $png = substr_replace($png, pack('NN', 9000, 9000), 16, 8);
        file_put_contents($this->source, $png);
        $image = $this->getMockBuilder(\CodeIgniter\Images\Handlers\GDHandler::class)
            ->disableOriginalConstructor()->onlyMethods(['withFile'])->getMock();
        $image->expects($this->never())->method('withFile');
        \Config\Services::injectMock('image', $image);
        $this->expectException(\RuntimeException::class);
        SafeImageUpload::store(new UploadedFile($this->source, 'synthetic.png'), 'uploads/users/profile/', true, $this->root);
    }
}
