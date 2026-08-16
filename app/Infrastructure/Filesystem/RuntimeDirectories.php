<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Filesystem;

use function dirname;

use EasyPrint\Infrastructure\Configuration\ConfigurationException;

use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;

final class RuntimeDirectories
{
    public static function ensure(string $databasePath, string $temporaryPath): void
    {
        self::ensureDirectory(dirname($databasePath), 'DATABASE_PATH');
        self::ensureDirectory($temporaryPath, 'TEMPORARY_PATH');
    }

    private static function ensureDirectory(string $path, string $setting): void
    {
        if (!is_dir($path) && !mkdir($path, 0o770, true) && !is_dir($path)) {
            throw new ConfigurationException(sprintf('The directory configured by %s could not be created.', $setting));
        }

        if (!is_writable($path)) {
            throw new ConfigurationException(sprintf('The directory configured by %s is not writable.', $setting));
        }
    }
}
