<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use DirectoryIterator;

use function filemtime;
use function is_file;
use function is_link;
use function is_string;
use function preg_match;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function realpath;

use Throwable;

use function time;
use function unlink;

final readonly class TemporaryUploadCleanup
{
    public function __construct(
        private string $storageDirectory,
        private int $ttlSeconds,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function run(?int $now = null): TemporaryUploadCleanupReport
    {
        $root = realpath($this->storageDirectory);

        if (false === $root || !is_dir($root)) {
            return new TemporaryUploadCleanupReport(0, 0, 0);
        }

        $deleted = 0;
        $skipped = 0;
        $failed = 0;
        $cutoff = ($now ?? time()) - $this->ttlSeconds;

        try {
            $entries = new DirectoryIterator($root);

            foreach ($entries as $entry) {
                if ($entry->isDot() || !$this->isManagedName($entry->getFilename())) {
                    ++$skipped;
                    continue;
                }

                $path = $entry->getPathname();

                if (is_link($path) || !is_file($path)) {
                    ++$skipped;
                    continue;
                }

                $resolved = realpath($path);
                $modified = filemtime($path);

                if (false === $resolved || false === $modified || !$this->isWithin($root, $resolved)) {
                    ++$skipped;
                    continue;
                }

                if ($modified > $cutoff) {
                    ++$skipped;
                    continue;
                }

                try {
                    if (unlink($resolved)) {
                        ++$deleted;
                    } else {
                        ++$failed;
                    }
                } catch (Throwable) {
                    ++$failed;
                }
            }
        } catch (Throwable) {
            ++$failed;
        }

        $this->logger->log(
            $failed > 0 ? LogLevel::WARNING : LogLevel::INFO,
            'temporary_upload_cleanup.completed',
            ['deleted' => $deleted, 'skipped' => $skipped, 'failed' => $failed],
        );

        return new TemporaryUploadCleanupReport($deleted, $skipped, $failed);
    }

    private function isManagedName(string $name): bool
    {
        return 1 === preg_match('/\A[0-9a-f]{32}\.(?:pdf|png|jpe?g)\z/D', $name);
    }

    private function isWithin(string $root, string $path): bool
    {
        $normalizedRoot = str_replace('\\', '/', rtrim($root, '/\\'));
        $normalizedPath = str_replace('\\', '/', $path);

        if (PHP_OS_FAMILY === 'Windows') {
            $normalizedRoot = strtolower($normalizedRoot);
            $normalizedPath = strtolower($normalizedPath);
        }

        return str_starts_with($normalizedPath, $normalizedRoot . '/');
    }
}
