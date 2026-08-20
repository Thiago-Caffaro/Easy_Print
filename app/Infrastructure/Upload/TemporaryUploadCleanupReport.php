<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

final readonly class TemporaryUploadCleanupReport
{
    public function __construct(
        public int $deleted,
        public int $skipped,
        public int $failed,
    ) {}
}
