<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Upload;

use function copy;
use function dirname;

use EasyPrint\Application\Document\PdfUploadFailure;
use EasyPrint\Infrastructure\Upload\PdfStructureInspector;
use EasyPrint\Infrastructure\Upload\SecurePdfUpload;

use function file_exists;
use function file_put_contents;
use function filesize;
use function glob;
use function is_file;
use function mkdir;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function rmdir;

use Slim\Psr7\UploadedFile;

use function str_repeat;
use function str_replace;
use function str_starts_with;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const UPLOAD_ERR_PARTIAL;

final class SecurePdfUploadTest extends TestCase
{
    private string $fixtures;
    private string $runtime;
    private string $storage;
    private string $public;

    protected function setUp(): void
    {
        $this->fixtures = dirname(__DIR__, 3) . '/Fixtures/Uploads';
        $this->runtime = sys_get_temp_dir() . '/easy-print-pdf-' . uniqid('', true);
        $this->storage = $this->runtime . '/private';
        $this->public = $this->runtime . '/public';
        mkdir($this->runtime);
        mkdir($this->storage);
        mkdir($this->public);
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

    public function testItStoresAValidPdfUnderARandomPrivateName(): void
    {
        $upload = $this->upload('valid-minimal.pdf', 'quarterly-report.pdf', clientType: 'text/plain');

        $result = $this->service()->store($upload);

        self::assertTrue($result->succeeded());
        self::assertNull($result->failure);
        self::assertNotNull($result->document);
        self::assertSame('quarterly-report.pdf', $result->document->originalName);
        self::assertSame('application/pdf', $result->document->mediaType);
        self::assertSame(1, preg_match('/^[a-f0-9]{32}\.pdf$/D', $result->document->storedName));
        self::assertNotSame('quarterly-report.pdf', $result->document->storedName);
        self::assertTrue(str_starts_with(
            str_replace('\\', '/', $result->document->absolutePath),
            str_replace('\\', '/', $this->storage),
        ));
        self::assertTrue(file_exists($result->document->absolutePath));
        self::assertSame([], glob($this->public . '/*'));
    }

    #[DataProvider('invalidNameProvider')]
    public function testItRejectsTraversalOrControlCharactersInTheOriginalName(string $name): void
    {
        $result = $this->service()->store($this->upload('valid-minimal.pdf', $name));

        self::assertSame(PdfUploadFailure::InvalidName, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function invalidNameProvider(): iterable
    {
        yield 'parent traversal' => ['../../report.pdf'];
        yield 'Windows path' => ['C:\\private\\report.pdf'];
        yield 'header delimiter' => ["report.pdf\r\nX-Unsafe: yes"];
    }

    public function testItRejectsAnExtensionMismatchBeforeMovingTheUpload(): void
    {
        $result = $this->service()->store($this->upload('valid-minimal.pdf', 'report.txt'));

        self::assertSame(PdfUploadFailure::InvalidExtension, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsServerDetectedMimeMismatchAndDeletesTheTemporaryCopy(): void
    {
        $result = $this->service()->store($this->upload('plain-text.pdf', 'report.pdf', clientType: 'application/pdf'));

        self::assertSame(PdfUploadFailure::MimeMismatch, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsATruncatedPdfAndDeletesTheTemporaryCopy(): void
    {
        $result = $this->service()->store($this->upload('truncated.pdf', 'report.pdf'));

        self::assertSame(PdfUploadFailure::InvalidPdf, $result->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItRejectsDeclaredAndActualOversizedFiles(): void
    {
        $fixtureSize = filesize($this->fixtures . '/valid-minimal.pdf');
        self::assertIsInt($fixtureSize);
        $declared = $this->service(maximumBytes: $fixtureSize - 1)->store(
            $this->upload('valid-minimal.pdf', 'report.pdf'),
        );
        self::assertSame(PdfUploadFailure::TooLarge, $declared->failure);

        $source = $this->runtime . '/large-source.pdf';
        file_put_contents($source, "%PDF-1.4\n" . str_repeat('A', 1_024));
        $actual = $this->service(maximumBytes: 128)->store(
            new UploadedFile($source, 'report.pdf', 'application/pdf', null),
        );
        self::assertSame(PdfUploadFailure::TooLarge, $actual->failure);
        self::assertSame([], glob($this->storage . '/*'));
    }

    public function testItMapsTransportErrorsWithoutExposingDetails(): void
    {
        $result = $this->service()->store(
            $this->upload('valid-minimal.pdf', 'report.pdf', error: UPLOAD_ERR_PARTIAL),
        );

        self::assertSame(PdfUploadFailure::UploadFailed, $result->failure);
    }

    public function testItRejectsStorageLocatedInsideThePublicWebroot(): void
    {
        $unsafeStorage = $this->public . '/uploads';
        mkdir($unsafeStorage);
        $service = new SecurePdfUpload(
            storageDirectory: $unsafeStorage,
            publicDirectory: $this->public,
            maximumBytes: 1_024,
            structureInspector: new PdfStructureInspector(),
        );

        $result = $service->store($this->upload('valid-minimal.pdf', 'report.pdf'));

        self::assertSame(PdfUploadFailure::StorageUnavailable, $result->failure);
        rmdir($unsafeStorage);
    }

    private function service(int $maximumBytes = 1_024): SecurePdfUpload
    {
        return new SecurePdfUpload(
            storageDirectory: $this->storage,
            publicDirectory: $this->public,
            maximumBytes: $maximumBytes,
            structureInspector: new PdfStructureInspector(),
        );
    }

    private function upload(
        string $fixture,
        string $clientName,
        string $clientType = 'application/pdf',
        int $error = UPLOAD_ERR_OK,
    ): UploadedFile {
        $source = $this->runtime . '/source-' . uniqid('', true);
        copy($this->fixtures . '/' . $fixture, $source);
        $size = filesize($source);
        self::assertIsInt($size);

        return new UploadedFile($source, $clientName, $clientType, $size, $error);
    }
}
