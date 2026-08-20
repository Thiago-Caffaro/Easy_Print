<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Upload;

use const DIRECTORY_SEPARATOR;

use EasyPrint\Infrastructure\Upload\TemporaryUploadCleanup;

use function file_put_contents;
use function is_file;
use function mkdir;

use PHPUnit\Framework\TestCase;

use function random_bytes;
use function sys_get_temp_dir;
use function time;
use function uniqid;

final class TemporaryUploadCleanupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'easy-print-cleanup-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->directory);
    }

    public function testItDeletesOnlyExpiredManagedUploads(): void
    {
        $old = $this->file('0123456789abcdef0123456789abcdef.pdf');
        $fresh = $this->file('fedcba9876543210fedcba9876543210.png');
        $unmanaged = $this->file('keep.txt');
        touch($old, 1_000);
        touch($fresh, 9_500);
        touch($unmanaged, 1);

        $report = new TemporaryUploadCleanup($this->directory, 3_000)->run(10_000);

        self::assertSame(1, $report->deleted);
        self::assertTrue($report->skipped >= 2);
        self::assertSame(0, $report->failed);
        self::assertFalse(is_file($old));
        self::assertTrue(is_file($fresh));
        self::assertTrue(is_file($unmanaged));
    }

    public function testItIgnoresMalformedNamesAndSymlinks(): void
    {
        $malformed = $this->file('not-a-managed-upload.pdf');
        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'easy-print-outside-' . uniqid('', true);
        file_put_contents($outside, random_bytes(4));
        $link = $this->directory . DIRECTORY_SEPARATOR . '0123456789abcdef0123456789abcdef.pdf';
        @symlink($outside, $link);

        $report = new TemporaryUploadCleanup($this->directory, 60)->run(time() + 120);

        self::assertSame(0, $report->deleted);
        self::assertTrue($report->skipped >= 1);
        self::assertTrue(is_file($outside));
        @unlink($outside);
        @unlink($malformed);
    }

    private function file(string $name): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, 'test');

        return $path;
    }
}
