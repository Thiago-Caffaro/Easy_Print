<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Security;

use function chmod;
use function fclose;
use function file_get_contents;
use function fopen;
use function fwrite;
use function random_bytes;

use RuntimeException;

use function strlen;
use function unlink;

final class RuntimeSecret
{
    private const LENGTH = 32;

    public static function loadOrCreate(string $path): string
    {
        $existing = self::read($path);

        if (null !== $existing) {
            return $existing;
        }

        $secret = random_bytes(self::LENGTH);
        $handle = @fopen($path, 'x+b');

        if (false === $handle) {
            $existing = self::read($path);

            if (null !== $existing) {
                return $existing;
            }

            throw new RuntimeException('The private runtime secret could not be created.');
        }

        if (!@chmod($path, 0o600)) {
            fclose($handle);
            @unlink($path);

            throw new RuntimeException('The private runtime secret permissions could not be restricted.');
        }

        if (self::LENGTH !== @fwrite($handle, $secret)) {
            fclose($handle);
            @unlink($path);

            throw new RuntimeException('The private runtime secret could not be written.');
        }

        fclose($handle);

        return $secret;
    }

    private static function read(string $path): ?string
    {
        $secret = @file_get_contents($path);

        if (false === $secret) {
            return null;
        }

        if (self::LENGTH !== strlen($secret)) {
            throw new RuntimeException('The private runtime secret is invalid.');
        }

        return $secret;
    }
}
