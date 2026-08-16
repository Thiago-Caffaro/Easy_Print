<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Upload;

use function copy;

use EasyPrint\Application\Document\ImageUploadFailure;
use EasyPrint\Infrastructure\Upload\ImageFileInspector;
use EasyPrint\Infrastructure\Upload\SecureImageUpload;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function glob;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefill;
use function imagejpeg;
use function imagepng;
use function is_file;
use function mkdir;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function rmdir;

use Slim\Psr7\UploadedFile;

use function str_replace;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const UPLOAD_ERR_PARTIAL;

final class SecureImageUploadTest extends TestCase
{
    private string $runtime;
    private string $storage;
    private string $public;
    private string $pngFixture;
    private string $jpegFixture;

    protected function setUp(): void
    {
        $this->runtime = sys_get_temp_dir() . '/easy-print-image-' . uniqid('', true);
        $this->storage = $this->runtime . '/private';
        $this->public = $this->runtime . '/public';
        $this->pngFixture = $this->runtime . '/valid.png';
        $this->jpegFixture = $this->runtime . '/valid.jpg';
        mkdir($this->runtime);
        mkdir($this->storage);
        mkdir($this->public);
        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        foreach ([$this->storage, $this->public, $this->runtime] as $directory) {
            $paths = glob($directory . '/*');

            if (false !== $paths) {
                foreach ($paths as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }

        rmdir($this->storage);
        rmdir($this->public);
        rmdir($this->runtime);
    }

    /**
     * @param 'image/jpeg'|'image/png' $mediaType
     */
    #[DataProvider('validImageProvider')]
    public function testItStoresDecodableImagesUnderRandomPrivateNames(
        string $fixtureProperty,
        string $clientName,
        string $mediaType,
        string $storedExtension,
    ): void {
        $fixture = $this->{$fixtureProperty};
        $result = $this->service()->store($this->upload($fixture, $clientName, 'text/plain'));

        self::assertTrue($result->succeeded());
        self::assertNull($result->failure);
        self::assertNotNull($result->document);
        self::assertSame($clientName, $result->document->originalName);
        self::assertSame($mediaType, $result->document->mediaType);
        self::assertSame(2, $result->document->pixelWidth);
        self::assertSame(2, $result->document->pixelHeight);
        self::assertSame(1, preg_match('/^[a-f0-9]{32}\.' . $storedExtension . '$/D', $result->document->storedName));
        self::assertTrue(str_starts_with(
            str_replace('\\', '/', $result->document->absolutePath),
            str_replace('\\', '/', $this->storage),
        ));
        self::assertTrue(file_exists($result->document->absolutePath));
        self::assertSame([], glob($this->public . '/*'));
    }

    /**
     * @return iterable<string,array{string,string,string,string}>
     */
    public static function validImageProvider(): iterable
    {
        yield 'PNG' => ['pngFixture', 'diagram.png', 'image/png', 'png'];
        yield 'JPEG' => ['jpegFixture', 'photo.jpeg', 'image/jpeg', 'jpg'];
    }

    public function testItRejectsExtensionAndDetectedMimeDisagreement(): void
    {
        $result = $this->service()->store($this->upload($this->pngFixture, 'photo.jpg', 'image/jpeg'));

        self::assertSame(ImageUploadFailure::MimeMismatch, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsUnsupportedExtensionsBeforeMovingTheUpload(): void
    {
        $result = $this->service()->store($this->upload($this->pngFixture, 'photo.gif', 'image/png'));

        self::assertSame(ImageUploadFailure::InvalidExtension, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsMalformedImagesAndDeletesTheTemporaryCopy(): void
    {
        $contents = file_get_contents($this->pngFixture);
        self::assertIsString($contents);
        $truncated = $this->runtime . '/truncated.png';
        file_put_contents($truncated, substr($contents, 0, -12));

        $result = $this->service()->store($this->upload($truncated, 'truncated.png'));

        self::assertSame(ImageUploadFailure::InvalidImage, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    #[DataProvider('trailingPayloadProvider')]
    public function testItRejectsPolyglotLikeTrailingPayloads(string $fixtureProperty, string $clientName): void
    {
        $contents = file_get_contents($this->{$fixtureProperty});
        self::assertIsString($contents);
        $polyglot = $this->runtime . '/polyglot-' . $clientName;
        file_put_contents($polyglot, $contents . "<?php echo 'unsafe';");

        $result = $this->service()->store($this->upload($polyglot, $clientName));

        self::assertSame(ImageUploadFailure::InvalidImage, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function trailingPayloadProvider(): iterable
    {
        yield 'PNG' => ['pngFixture', 'polyglot.png'];
        yield 'JPEG' => ['jpegFixture', 'polyglot.jpg'];
    }

    public function testItEnforcesDimensionAndPixelLimitsBeforeDecoding(): void
    {
        $width = $this->service(maximumWidth: 1)->store($this->upload($this->pngFixture, 'wide.png'));
        self::assertSame(ImageUploadFailure::DimensionsTooLarge, $width->failure);

        $pixels = $this->service(maximumPixels: 3)->store($this->upload($this->jpegFixture, 'dense.jpg'));
        self::assertSame(ImageUploadFailure::DimensionsTooLarge, $pixels->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsDeclaredAndActualOversizedFiles(): void
    {
        $size = filesize($this->pngFixture);
        self::assertIsInt($size);

        $declared = $this->service(maximumBytes: $size - 1)->store($this->upload($this->pngFixture, 'large.png'));
        self::assertSame(ImageUploadFailure::TooLarge, $declared->failure);

        $actual = $this->service(maximumBytes: $size - 1)->store(
            new UploadedFile($this->copySource($this->pngFixture), 'large.png', 'image/png', null),
        );
        self::assertSame(ImageUploadFailure::TooLarge, $actual->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsUnsafeNamesAndTransportErrors(): void
    {
        $name = $this->service()->store($this->upload($this->pngFixture, '../../image.png'));
        self::assertSame(ImageUploadFailure::InvalidName, $name->failure);

        $transport = $this->service()->store(
            $this->upload($this->pngFixture, 'image.png', error: UPLOAD_ERR_PARTIAL),
        );
        self::assertSame(ImageUploadFailure::UploadFailed, $transport->failure);
    }

    public function testItRejectsStorageLocatedInsideThePublicWebroot(): void
    {
        $unsafeStorage = $this->public . '/uploads';
        mkdir($unsafeStorage);
        $service = $this->service(storage: $unsafeStorage);

        $result = $service->store($this->upload($this->pngFixture, 'image.png'));

        self::assertSame(ImageUploadFailure::StorageUnavailable, $result->failure);
        rmdir($unsafeStorage);
    }

    private function service(
        int $maximumBytes = 1_024_000,
        int $maximumWidth = 100,
        int $maximumHeight = 100,
        int $maximumPixels = 10_000,
        ?string $storage = null,
    ): SecureImageUpload {
        return new SecureImageUpload(
            storageDirectory: $storage ?? $this->storage,
            publicDirectory: $this->public,
            maximumBytes: $maximumBytes,
            maximumWidth: $maximumWidth,
            maximumHeight: $maximumHeight,
            maximumPixels: $maximumPixels,
            inspector: new ImageFileInspector(),
        );
    }

    private function upload(
        string $fixture,
        string $clientName,
        string $clientType = 'image/png',
        int $error = UPLOAD_ERR_OK,
    ): UploadedFile {
        $source = $this->copySource($fixture);
        $size = filesize($source);
        self::assertIsInt($size);

        return new UploadedFile($source, $clientName, $clientType, $size, $error);
    }

    private function copySource(string $fixture): string
    {
        $source = $this->runtime . '/source-' . uniqid('', true);
        self::assertTrue(copy($fixture, $source));

        return $source;
    }

    private function createFixtures(): void
    {
        $image = imagecreatetruecolor(2, 2);
        $color = imagecolorallocate($image, 18, 113, 105);

        if (false === $color) {
            self::fail('Unable to allocate a fixture color.');
        }

        imagefill($image, 0, 0, $color);
        self::assertTrue(imagepng($image, $this->pngFixture));
        self::assertTrue(imagejpeg($image, $this->jpegFixture, 90));
    }
}
