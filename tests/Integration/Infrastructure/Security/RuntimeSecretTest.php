<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Security;

use EasyPrint\Infrastructure\Security\RuntimeSecret;

use function file_put_contents;
use function mkdir;

use PHPUnit\Framework\TestCase;

use function rmdir;

use RuntimeException;

use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RuntimeSecretTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/easy-print-secret-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (is_file($this->directory . '/secret')) {
            unlink($this->directory . '/secret');
        }

        rmdir($this->directory);
    }

    public function testItCreatesAndReusesAPrivateRandomSecret(): void
    {
        $path = $this->directory . '/secret';

        $created = RuntimeSecret::loadOrCreate($path);
        $loaded = RuntimeSecret::loadOrCreate($path);

        self::assertSame(32, strlen($created));
        self::assertSame($created, $loaded);
    }

    public function testItRejectsAnInvalidExistingSecret(): void
    {
        $path = $this->directory . '/secret';
        file_put_contents($path, 'too-short');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private runtime secret is invalid');

        RuntimeSecret::loadOrCreate($path);
    }
}
