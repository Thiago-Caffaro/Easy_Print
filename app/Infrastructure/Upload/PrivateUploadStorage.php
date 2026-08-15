<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use function bin2hex;

use const DIRECTORY_SEPARATOR;

use function file_exists;
use function filesize;
use function is_int;
use function is_writable;
use function preg_match;

use Psr\Http\Message\UploadedFileInterface;

use function random_bytes;
use function realpath;
use function rtrim;
use function str_replace;
use function strtolower;

use Throwable;

use function unlink;

final readonly class PrivateUploadStorage
{
    public function __construct(
        private string $storageDirectory,
        private string $publicDirectory,
    ) {}

    public function move(UploadedFileInterface $upload, string $extension): ?PrivateStoredUpload
    {
        if (1 !== preg_match('/^[a-z0-9]{1,8}$/D', $extension)) {
            return null;
        }

        $storageRoot = $this->safeStorageRoot();

        if (null === $storageRoot) {
            return null;
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $storageRoot . DIRECTORY_SEPARATOR . $storedName;

        if (file_exists($target)) {
            return null;
        }

        try {
            $upload->moveTo($target);
        } catch (Throwable) {
            return null;
        }

        $resolvedTarget = realpath($target);

        if (false === $resolvedTarget || !$this->isWithin($storageRoot, $resolvedTarget)) {
            @unlink($target);

            return null;
        }

        $byteSize = filesize($resolvedTarget);

        if (!is_int($byteSize)) {
            @unlink($resolvedTarget);

            return null;
        }

        return new PrivateStoredUpload($storedName, $resolvedTarget, $byteSize);
    }

    public function delete(PrivateStoredUpload $stored): void
    {
        @unlink($stored->absolutePath);
    }

    private function safeStorageRoot(): ?string
    {
        $storageRoot = realpath($this->storageDirectory);
        $publicRoot = realpath($this->publicDirectory);

        if (false === $storageRoot || false === $publicRoot || !is_writable($storageRoot)) {
            return null;
        }

        $storage = $this->normalizedPath($storageRoot);
        $public = $this->normalizedPath($publicRoot);

        if ($storage === $public || str_starts_with($storage . '/', $public . '/')) {
            return null;
        }

        return $storageRoot;
    }

    private function isWithin(string $root, string $path): bool
    {
        return str_starts_with($this->normalizedPath($path), $this->normalizedPath($root) . '/');
    }

    private function normalizedPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return rtrim(PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized, '/');
    }
}
